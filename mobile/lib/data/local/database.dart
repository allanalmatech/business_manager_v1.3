import 'dart:async';

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

/// The phone's own copy of the shop. Screens read from here and never wait for
/// the network; the sync engine is the only thing that talks to the server.
class LocalDb {
  LocalDb._();

  static final LocalDb instance = LocalDb._();
  static const int _version = 1;

  Database? _db;
  Completer<Database>? _opening;

  Future<Database> get db async {
    final Database? open = _db;
    if (open != null) return open;
    final Completer<Database>? waiting = _opening;
    if (waiting != null) return waiting.future;

    final Completer<Database> c = Completer<Database>();
    _opening = c;
    try {
      final String dir = (await getApplicationDocumentsDirectory()).path;
      final Database opened = await openDatabase(
        p.join(dir, 'business_manager.db'),
        version: _version,
        onConfigure: (Database d) => d.execute('PRAGMA foreign_keys = ON'),
        onCreate: (Database d, int v) async => _createAll(d),
        onUpgrade: (Database d, int from, int to) async {
          // Future versions add their steps here. The catalogue can always be
          // rebuilt from the server, but the outbox must never be dropped.
        },
      );
      _db = opened;
      c.complete(opened);
    } catch (e) {
      c.completeError(e);
      _opening = null;
      rethrow;
    }
    return c.future;
  }

  static Future<void> _createAll(Database d) async {
    final Batch b = d.batch();

    // ---- catalogue, replaced by sync ----
    b.execute('''CREATE TABLE products (
      id INTEGER PRIMARY KEY, category_id INTEGER, brand_id INTEGER,
      name TEXT NOT NULL, sku TEXT, barcode TEXT, unit_type TEXT, unit_name TEXT,
      pieces_per_box INTEGER, wholesale_price REAL NOT NULL DEFAULT 0,
      retail_price REAL NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1,
      image TEXT, updated_at TEXT)''');
    b.execute('CREATE INDEX idx_products_name ON products(name)');
    b.execute('CREATE INDEX idx_products_barcode ON products(barcode)');
    b.execute('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER)');
    b.execute('CREATE TABLE brands (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
    b.execute('''CREATE TABLE customers (
      id INTEGER PRIMARY KEY, name TEXT NOT NULL, phone TEXT, email TEXT, address TEXT,
      credit_limit REAL NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1)''');
    b.execute('CREATE INDEX idx_customers_name ON customers(name)');
    b.execute('CREATE TABLE locations (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER)');
    b.execute('''CREATE TABLE stock (
      product_id INTEGER NOT NULL, location_id INTEGER NOT NULL,
      qty REAL NOT NULL DEFAULT 0, low_level REAL NOT NULL DEFAULT 0,
      PRIMARY KEY (product_id, location_id))''');

    // ---- the device's own work, never overwritten by sync ----
    // Everything the device changes becomes one outbox row, applied on the
    // server exactly once because of its uuid.
    b.execute('''CREATE TABLE outbox (
      uuid TEXT PRIMARY KEY, kind TEXT NOT NULL, payload TEXT NOT NULL,
      created_at TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending',
      attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT, result TEXT,
      seq INTEGER)''');
    b.execute('CREATE INDEX idx_outbox_status ON outbox(status, seq)');

    // What a sale looked like on this phone, so a receipt can be reprinted
    // whether or not the server has seen it yet.
    b.execute('''CREATE TABLE local_sales (
      uuid TEXT PRIMARY KEY, client_ref TEXT NOT NULL, location_id INTEGER NOT NULL,
      customer_id INTEGER, customer_ref TEXT, customer_name TEXT,
      doc_type TEXT NOT NULL DEFAULT 'receipt', pricing_mode TEXT NOT NULL DEFAULT 'retail',
      subtotal REAL NOT NULL, discount REAL NOT NULL, tax REAL NOT NULL, total REAL NOT NULL,
      paid REAL NOT NULL, change_due REAL NOT NULL, balance REAL NOT NULL,
      created_at TEXT NOT NULL, sync_status TEXT NOT NULL DEFAULT 'pending',
      server_doc_no TEXT, server_id INTEGER, review_note TEXT, error TEXT)''');
    b.execute('CREATE INDEX idx_local_sales_created ON local_sales(created_at DESC)');
    b.execute('''CREATE TABLE local_sale_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT, sale_uuid TEXT NOT NULL,
      product_id INTEGER, name TEXT NOT NULL, sku TEXT, qty REAL NOT NULL,
      unit_price REAL NOT NULL, discount REAL NOT NULL DEFAULT 0, line_total REAL NOT NULL,
      FOREIGN KEY (sale_uuid) REFERENCES local_sales(uuid) ON DELETE CASCADE)''');
    b.execute('CREATE INDEX idx_local_sale_items_sale ON local_sale_items(sale_uuid)');
    b.execute('''CREATE TABLE local_sale_payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT, sale_uuid TEXT NOT NULL,
      method TEXT NOT NULL, reference TEXT, amount REAL NOT NULL,
      FOREIGN KEY (sale_uuid) REFERENCES local_sales(uuid) ON DELETE CASCADE)''');

    // Stock the device has used up but not yet confirmed with the server.
    // On-hand shown in the app = stock.qty + this. Cleared when the server
    // confirms, so two phones never double-count the same deduction.
    b.execute('''CREATE TABLE stock_pending (
      uuid TEXT NOT NULL, product_id INTEGER NOT NULL, location_id INTEGER NOT NULL,
      delta REAL NOT NULL, PRIMARY KEY (uuid, product_id, location_id))''');
    b.execute('CREATE INDEX idx_stock_pending_product ON stock_pending(product_id, location_id)');

    // Downloaded photos. Least recently looked at is thrown away first.
    b.execute('''CREATE TABLE image_cache (
      url TEXT PRIMARY KEY, path TEXT NOT NULL, bytes INTEGER NOT NULL,
      last_used INTEGER NOT NULL)''');
    b.execute('CREATE INDEX idx_image_cache_used ON image_cache(last_used)');

    b.execute('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT)');
    await b.commit(noResult: true);
  }

  Future<String?> getMeta(String key) async {
    final List<Map<String, Object?>> rows =
        await (await db).query('meta', where: 'key = ?', whereArgs: <Object?>[key], limit: 1);
    return rows.isEmpty ? null : rows.first['value'] as String?;
  }

  Future<void> setMeta(String key, String value) async {
    await (await db).insert('meta', <String, Object?>{'key': key, 'value': value},
        conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<int> getMetaInt(String key, {int fallback = 0}) async =>
      int.tryParse(await getMeta(key) ?? '') ?? fallback;

  /// Signing out wipes the shop's data from the phone but keeps anything the
  /// device still owes the server, so a sale made offline is never lost.
  Future<void> clearCatalogue() async {
    final Database d = await db;
    final Batch b = d.batch();
    for (final String t in <String>['products', 'categories', 'brands', 'customers', 'locations', 'stock']) {
      b.delete(t);
    }
    b.delete('meta', where: 'key = ?', whereArgs: <Object?>['sync_version']);
    await b.commit(noResult: true);
  }

  Future<int> pendingWorkCount() async {
    final List<Map<String, Object?>> rows = await (await db).rawQuery(
        "SELECT COUNT(*) AS n FROM outbox WHERE status IN ('pending','failed')");
    return (rows.first['n'] as int?) ?? 0;
  }
}
