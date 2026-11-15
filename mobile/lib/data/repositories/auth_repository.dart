import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/result.dart';
import '../../domain/models/catalog.dart';
import '../local/database.dart';
import '../remote/api_client.dart';

/// Signing in, signing out, and remembering who is signed in between launches.
/// The token lives in the phone's keystore, never in the app database.
class AuthRepository {
  AuthRepository(this._api, this._db, {FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const String _tokenKey = 'bm_device_token';

  final ApiClient _api;
  final LocalDb _db;
  final FlutterSecureStorage _storage;

  Session? session;

  bool get signedIn => _api.token != null;

  /// Restores the last session so the app opens straight into the till, even
  /// with no network: the saved details are enough to sell offline.
  Future<Session?> restore() async {
    final String? token = await _storage.read(key: _tokenKey);
    if (token == null) return null;
    _api.token = token;
    final String? raw = await _db.getMeta('session');
    if (raw == null) return null;
    try {
      final Map<String, Object?> j = jsonDecode(raw) as Map<String, Object?>;
      session = Session.fromJson(j, deviceId: await _db.getMetaInt('device_id'));
      return session;
    } catch (_) {
      return null;
    }
  }

  Future<Result<Session>> signIn(String username, String password, String deviceName) async {
    final Result<Map<String, Object?>> res = await _api.post('auth/login', <String, Object?>{
      'username': username,
      'password': password,
      'device_name': deviceName,
    });
    if (res is Err<Map<String, Object?>>) return Err<Session>(res.message, offline: res.offline, retryable: res.retryable);

    final Map<String, Object?> body = (res as Ok<Map<String, Object?>>).value;
    final String token = (body['token'] as String?) ?? '';
    if (token.isEmpty) return const Err<Session>('The server did not return a sign-in token.');

    final int deviceId = (body['device_id'] as num?)?.toInt() ?? 0;
    await _storage.write(key: _tokenKey, value: token);
    _api.token = token;

    final Session s = Session.fromJson(body, deviceId: deviceId);
    session = s;
    await _db.setMeta('session', jsonEncode(s.toJson()));
    await _db.setMeta('device_id', '$deviceId');
    return Ok<Session>(s);
  }

  /// Refreshes the shop's settings. Failing is fine: the saved copy still works.
  Future<void> refreshContext() async {
    final Result<Map<String, Object?>> res = await _api.get('bootstrap');
    if (res is! Ok<Map<String, Object?>>) return;
    final Session s = Session.fromJson(res.value, deviceId: await _db.getMetaInt('device_id'));
    session = s;
    await _db.setMeta('session', jsonEncode(s.toJson()));
  }

  /// Refuses while the phone still owes the server work, so a sale made offline
  /// cannot be thrown away by signing out.
  Future<Result<void>> signOut({bool force = false}) async {
    final int pending = await _db.pendingWorkCount();
    if (pending > 0 && !force) {
      return Err<void>('$pending sale(s) or change(s) have not reached the server yet. Connect and sync first.', retryable: false);
    }
    await _api.post('auth/logout');
    await _storage.delete(key: _tokenKey);
    _api.token = null;
    session = null;
    await _db.clearCatalogue();
    await _db.setMeta('session', '');
    return const Ok<void>(null);
  }
}
