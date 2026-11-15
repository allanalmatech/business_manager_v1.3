import 'dart:async';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

import '../../core/config.dart';
import 'database.dart';

/// Product photos kept on the phone.
///
/// A photo is downloaded once and reused, which matters on a paid connection:
/// the same shelf of products is looked at all day. Every use updates a
/// timestamp, and when the folder grows past its budget the ones nobody has
/// looked at for longest are deleted first, so storage cannot creep upwards.
class ProductImageCache {
  ProductImageCache({required LocalDb db, required String baseUrl, http.Client? client})
      : _db = db,
        _baseUrl = baseUrl.replaceAll(RegExp(r'/+$'), ''),
        _client = client ?? http.Client();

  final LocalDb _db;
  final String _baseUrl;
  final http.Client _client;
  final Map<String, Future<File?>> _inFlight = <String, Future<File?>>{};
  Directory? _dir;

  Future<Directory> _folder() async {
    final Directory? cached = _dir;
    if (cached != null) return cached;
    final Directory d = Directory(p.join((await getApplicationSupportDirectory()).path, 'product_images'));
    if (!await d.exists()) await d.create(recursive: true);
    _dir = d;
    return d;
  }

  /// Turns whatever the server stored into a full address: a path on the shop's
  /// own server, or an address somewhere else left as it is.
  String? resolve(String? image) {
    if (image == null) return null;
    String v = image.trim();
    if (v.isEmpty || v == '[]') return null;
    v = v.replaceFirst(RegExp(r'^(https?):/(?!/)', caseSensitive: false), r'$1://');
    if (v.startsWith('http://') || v.startsWith('https://')) return v;
    return '$_baseUrl/${v.replaceAll(RegExp(r'^/+'), '')}';
  }

  /// The file for a photo, downloading it if this phone has not seen it before.
  /// Returns null when there is no photo or it cannot be fetched; callers show
  /// a placeholder rather than an error.
  Future<File?> fileFor(String? image) {
    final String? url = resolve(image);
    if (url == null) return Future<File?>.value();
    final Future<File?>? running = _inFlight[url];
    if (running != null) return running;
    final Future<File?> job = _fetch(url).whenComplete(() => _inFlight.remove(url));
    _inFlight[url] = job;
    return job;
  }

  Future<File?> _fetch(String url) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows =
        await d.query('image_cache', where: 'url = ?', whereArgs: <Object?>[url], limit: 1);

    if (rows.isNotEmpty) {
      final File f = File(rows.first['path'] as String);
      if (await f.exists()) {
        await _touch(d, url);
        return f;
      }
      await d.delete('image_cache', where: 'url = ?', whereArgs: <Object?>[url]);
    }

    try {
      final http.Response res = await _client.get(Uri.parse(url)).timeout(AppConfig.requestTimeout);
      if (res.statusCode != 200 || res.bodyBytes.isEmpty) return null;
      final Directory dir = await _folder();
      final File file = File(p.join(dir.path, '${_hash(url)}${p.extension(Uri.parse(url).path)}'));
      await file.writeAsBytes(res.bodyBytes, flush: true);
      await d.insert(
          'image_cache',
          <String, Object?>{
            'url': url,
            'path': file.path,
            'bytes': res.bodyBytes.length,
            'last_used': DateTime.now().millisecondsSinceEpoch,
          },
          conflictAlgorithm: ConflictAlgorithm.replace);
      unawaited(evictIfNeeded());
      return file;
    } catch (_) {
      return null; // no connection, or the file has gone: show the placeholder
    }
  }

  Future<void> _touch(Database d, String url) async {
    await d.update('image_cache', <String, Object?>{'last_used': DateTime.now().millisecondsSinceEpoch},
        where: 'url = ?', whereArgs: <Object?>[url]);
  }

  /// Deletes the least recently looked at photos until the folder is back
  /// inside its budget.
  Future<void> evictIfNeeded() async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> totalRow = await d.rawQuery('SELECT COALESCE(SUM(bytes), 0) AS total FROM image_cache');
    int total = ((totalRow.first['total'] as num?) ?? 0).toInt();
    if (total <= AppConfig.imageCacheBudgetBytes) return;

    final List<Map<String, Object?>> oldest =
        await d.query('image_cache', orderBy: 'last_used ASC', limit: 400);
    for (final Map<String, Object?> row in oldest) {
      if (total <= AppConfig.imageCacheBudgetBytes * 0.8) break;
      try {
        final File f = File(row['path'] as String);
        if (await f.exists()) await f.delete();
      } catch (_) {/* already gone */}
      await d.delete('image_cache', where: 'url = ?', whereArgs: <Object?>[row['url']]);
      total -= ((row['bytes'] as num?) ?? 0).toInt();
    }
  }

  Future<int> sizeBytes() async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> r = await d.rawQuery('SELECT COALESCE(SUM(bytes), 0) AS total FROM image_cache');
    return ((r.first['total'] as num?) ?? 0).toInt();
  }

  Future<void> clear() async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows = await d.query('image_cache');
    for (final Map<String, Object?> row in rows) {
      try {
        final File f = File(row['path'] as String);
        if (await f.exists()) await f.delete();
      } catch (_) {/* already gone */}
    }
    await d.delete('image_cache');
  }

  /// FNV-1a: a short, stable name for a URL without pulling in a crypto package.
  static String _hash(String s) {
    int h = 0x811c9dc5;
    for (final int c in s.codeUnits) {
      h = (h ^ c) & 0xffffffff;
      h = (h * 0x01000193) & 0xffffffff;
    }
    return h.toRadixString(16).padLeft(8, '0') + s.length.toRadixString(16);
  }
}
