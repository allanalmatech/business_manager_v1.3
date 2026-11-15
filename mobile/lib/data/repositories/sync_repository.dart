import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:sqflite/sqflite.dart';

import '../../core/config.dart';
import '../../core/result.dart';
import '../local/dao.dart';
import '../local/database.dart';
import '../remote/api_client.dart';

enum SyncState { idle, syncing, offline, failed }

class SyncStatusInfo {
  const SyncStatusInfo({
    required this.state,
    required this.pending,
    this.lastSyncAt,
    this.message,
    this.rejected = 0,
  });

  final SyncState state;
  final int pending;
  final int rejected;
  final DateTime? lastSyncAt;
  final String? message;
}

/// Keeps the phone and the server in step.
///
/// Pull: ask for everything changed since the version we last stored, save the
/// page, then store the new version. A dropped connection repeats a page
/// rather than skipping one.
///
/// Push: send queued actions oldest first. Each carries a uuid, so the server
/// applies it once however many times it arrives. Results are recorded against
/// the queue, and only then is the phone's reservation of stock released.
class SyncRepository extends ChangeNotifier {
  SyncRepository({
    required ApiClient api,
    required LocalDb db,
    required OutboxDao outbox,
    required SalesDao sales,
  })  : _api = api,
        _db = db,
        _outbox = outbox,
        _sales = sales;

  final ApiClient _api;
  final LocalDb _db;
  final OutboxDao _outbox;
  final SalesDao _sales;

  SyncState _state = SyncState.idle;
  String? _message;
  DateTime? _lastSyncAt;
  int _pending = 0;
  int _rejected = 0;
  Timer? _timer;
  Duration _backoff = AppConfig.syncRetryBase;

  /// Only one sync at a time; a second caller waits for the first to finish.
  Future<void>? _running;

  SyncStatusInfo get status => SyncStatusInfo(
        state: _state,
        pending: _pending,
        rejected: _rejected,
        lastSyncAt: _lastSyncAt,
        message: _message,
      );

  /// Set when the server stops accepting this device's token, or the person
  /// signs out here. The app watches this to return to the sign-in screen.
  bool signedOutByServer = false;

  void markSignedOut() {
    signedOutByServer = true;
    _timer?.cancel();
    notifyListeners();
  }

  void start() {
    _timer?.cancel();
    _timer = Timer.periodic(AppConfig.syncInterval, (_) => syncNow());
    unawaited(refreshCounts());
    unawaited(syncNow());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> refreshCounts() async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> r = await d.rawQuery(
        "SELECT SUM(status IN ('pending','failed')) AS pending, SUM(status = 'rejected') AS rejected FROM outbox");
    _pending = ((r.first['pending'] as num?) ?? 0).toInt();
    _rejected = ((r.first['rejected'] as num?) ?? 0).toInt();
    notifyListeners();
  }

  Future<void> syncNow({bool force = false}) async {
    final Future<void>? running = _running;
    if (running != null) return running;
    final Completer<void> c = Completer<void>();
    _running = c.future;
    try {
      await _sync();
    } finally {
      _running = null;
      c.complete();
    }
  }

  Future<void> _sync() async {
    if (_api.token == null) return;
    _set(SyncState.syncing, null);

    // Push first: the server should know what happened here before we take its
    // view of stock, or the phone would show figures that ignore its own sales.
    final Result<void> pushed = await _push();
    if (pushed is Err<void>) {
      _fail(pushed);
      return;
    }
    final Result<void> pulled = await _pull();
    if (pulled is Err<void>) {
      _fail(pulled);
      return;
    }

    await _outbox.prune();
    await refreshCounts();
    _backoff = AppConfig.syncRetryBase;
    _lastSyncAt = DateTime.now();
    _set(SyncState.idle, null);
  }

  void _fail(Err<void> e) {
    if (e.unauthenticated) {
      signedOutByServer = true;
      _set(SyncState.failed, e.message);
      return;
    }
    _set(e.offline ? SyncState.offline : SyncState.failed, e.message);
    // Back off so a phone with no signal does not hammer the radio flat
    _backoff = Duration(
      seconds: (_backoff.inSeconds * 2).clamp(
        AppConfig.syncRetryBase.inSeconds,
        AppConfig.syncRetryMax.inSeconds,
      ),
    );
    Timer(_backoff, () => syncNow());
  }

  void _set(SyncState s, String? message) {
    _state = s;
    _message = message;
    notifyListeners();
  }

  /* ---------------- push ---------------- */

  Future<Result<void>> _push() async {
    while (true) {
      final List<OutboxEntry> batch = await _outbox.due(limit: 25);
      if (batch.isEmpty) return const Ok<void>(null);

      final Result<Map<String, Object?>> res = await _api.post('sync/push', <String, Object?>{
        'mutations': batch.map((OutboxEntry e) => e.toWire()).toList(),
      });
      if (res is Err<Map<String, Object?>>) {
        return Err<void>(res.message, offline: res.offline, unauthenticated: res.unauthenticated, retryable: res.retryable);
      }

      final List<Object?> results = ((res as Ok<Map<String, Object?>>).value['results'] as List<Object?>?) ?? <Object?>[];
      bool progressed = false;
      for (final Object? raw in results) {
        if (raw is! Map<String, Object?>) continue;
        final String uuid = (raw['uuid'] as String?) ?? '';
        final String status = (raw['status'] as String?) ?? 'retry';
        final OutboxEntry? entry = batch.where((OutboxEntry e) => e.uuid == uuid).firstOrNull;

        switch (status) {
          case 'applied':
            progressed = true;
            await _outbox.markApplied(uuid, raw);
            if (entry?.kind == 'sale.create') await _sales.markSynced(uuid, raw);
            break;
          case 'rejected':
            progressed = true;
            final String why = (raw['error'] as String?) ?? 'The server would not accept it.';
            await _outbox.markRejected(uuid, why);
            if (entry?.kind == 'sale.create') await _sales.markRejected(uuid, why);
            break;
          default: // retry: leave it queued
            await _outbox.markFailed(uuid, (raw['error'] as String?) ?? 'Will try again.');
        }
      }
      await refreshCounts();
      // Nothing moved: stop rather than loop on the same batch forever
      if (!progressed) return const Ok<void>(null);
      if (batch.length < 25) return const Ok<void>(null);
    }
  }

  /* ---------------- pull ---------------- */

  Future<Result<void>> _pull() async {
    for (int page = 0; page < 50; page++) {
      final int since = await _db.getMetaInt('sync_version');
      final Result<Map<String, Object?>> res = await _api.get('sync/pull', query: <String, String>{
        'since': '$since',
        'limit': '${AppConfig.syncPageSize}',
      });
      if (res is Err<Map<String, Object?>>) {
        return Err<void>(res.message, offline: res.offline, unauthenticated: res.unauthenticated, retryable: res.retryable);
      }
      final Map<String, Object?> body = (res as Ok<Map<String, Object?>>).value;
      final bool full = body['full'] == true;
      final Map<String, Object?> changes = (body['changes'] as Map<String, Object?>?) ?? <String, Object?>{};

      await _applyChanges(changes, replaceEverything: full);

      // Saved first, then the marker: a crash in between costs one repeated page.
      await _db.setMeta('sync_version', '${(body['next'] as num?)?.toInt() ?? since}');
      if (body['has_more'] != true) return const Ok<void>(null);
    }
    return const Ok<void>(null);
  }

  static const Map<String, String> _tables = <String, String>{
    'location': 'locations',
    'category': 'categories',
    'brand': 'brands',
    'customer': 'customers',
    'product': 'products',
    'stock': 'stock',
  };

  Future<void> _applyChanges(Map<String, Object?> changes, {bool replaceEverything = false}) async {
    final Database d = await _db.db;
    await d.transaction((Transaction t) async {
      if (replaceEverything) {
        for (final String table in _tables.values) {
          await t.delete(table);
        }
      }
      for (final MapEntry<String, Object?> entry in changes.entries) {
        final String? table = _tables[entry.key];
        if (table == null || entry.value is! Map<String, Object?>) continue;
        final Map<String, Object?> part = entry.value as Map<String, Object?>;

        for (final Object? row in (part['upserts'] as List<Object?>?) ?? <Object?>[]) {
          if (row is! Map<String, Object?>) continue;
          await t.insert(table, _rowFor(entry.key, row), conflictAlgorithm: ConflictAlgorithm.replace);
        }
        for (final Object? key in (part['deletes'] as List<Object?>?) ?? <Object?>[]) {
          if (entry.key == 'stock') {
            final List<String> parts = '$key'.split(':');
            if (parts.length != 2) continue;
            await t.delete(table,
                where: 'product_id = ? AND location_id = ?',
                whereArgs: <Object?>[int.tryParse(parts[0]) ?? 0, int.tryParse(parts[1]) ?? 0]);
          } else {
            await t.delete(table, where: 'id = ?', whereArgs: <Object?>[int.tryParse('$key') ?? 0]);
          }
        }
      }
    });
  }

  /// Keeps only the columns the phone stores, so a newer server that sends more
  /// fields does not break an older app.
  Map<String, Object?> _rowFor(String entity, Map<String, Object?> r) {
    num? asNum(Object? v) => v is num ? v : num.tryParse('${v ?? ''}');
    switch (entity) {
      case 'location':
        return <String, Object?>{'id': r['id'], 'name': r['name'], 'is_active': r['is_active'] ?? 1};
      case 'category':
        return <String, Object?>{'id': r['id'], 'name': r['name'], 'is_active': r['is_active'] ?? 1};
      case 'brand':
        return <String, Object?>{'id': r['id'], 'name': r['name'], 'status': r['status']};
      case 'customer':
        return <String, Object?>{
          'id': r['id'], 'name': r['name'], 'phone': r['phone'], 'email': r['email'],
          'address': r['address'], 'credit_limit': asNum(r['credit_limit']) ?? 0, 'is_active': r['is_active'] ?? 1,
        };
      case 'product':
        return <String, Object?>{
          'id': r['id'], 'category_id': r['category_id'], 'brand_id': r['brand_id'], 'name': r['name'],
          'sku': r['sku'], 'barcode': r['barcode'], 'unit_type': r['unit_type'], 'unit_name': r['unit_name'],
          'pieces_per_box': asNum(r['pieces_per_box'])?.toInt() ?? 0,
          'wholesale_price': asNum(r['wholesale_price']) ?? 0, 'retail_price': asNum(r['retail_price']) ?? 0,
          'is_active': r['is_active'] ?? 1, 'image': r['image'], 'updated_at': r['updated_at'],
        };
      case 'stock':
        return <String, Object?>{
          'product_id': r['product_id'], 'location_id': r['location_id'],
          'qty': asNum(r['qty_base']) ?? asNum(r['qty']) ?? 0,
          'low_level': asNum(r['low_level_base']) ?? asNum(r['low_level']) ?? 0,
        };
    }
    return r;
  }

  /// Clears everything the phone knows and pulls a fresh copy. Queued work is
  /// pushed first so nothing is lost.
  Future<Result<void>> resetAndResync() async {
    final Result<void> pushed = await _push();
    if (pushed is Err<void>) return pushed;
    await _db.clearCatalogue();
    await _db.setMeta('sync_version', '0');
    final Result<void> pulled = await _pull();
    if (pulled is Ok<void>) {
      _lastSyncAt = DateTime.now();
      _set(SyncState.idle, null);
    }
    return pulled;
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
