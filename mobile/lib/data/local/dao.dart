import 'dart:convert';

import 'package:sqflite/sqflite.dart';

import '../../domain/models/catalog.dart';
import '../../domain/models/sale.dart';
import 'database.dart';

/// Every read a screen needs, in one place. Each method answers from the
/// phone's own database, so screens never block on the network.
class CatalogDao {
  CatalogDao(this._db);

  final LocalDb _db;

  /// On-hand quantity always subtracts what this phone has sold but not yet
  /// confirmed, so a cashier cannot sell the same unit twice while offline.
  static const String _qtyExpr =
      '(s.qty + COALESCE((SELECT SUM(sp.delta) FROM stock_pending sp '
      'WHERE sp.product_id = p.id AND sp.location_id = s.location_id), 0))';

  Future<List<Product>> search(String query, int locationId, {int limit = 60}) async {
    final Database d = await _db.db;
    final String q = query.trim();
    final List<Map<String, Object?>> rows;
    if (q.isEmpty) {
      rows = await d.rawQuery(
          'SELECT p.*, $_qtyExpr AS qty FROM products p '
          'LEFT JOIN stock s ON s.product_id = p.id AND s.location_id = ? '
          'WHERE p.is_active = 1 ORDER BY p.name LIMIT ?',
          <Object?>[locationId, limit]);
    } else {
      final String like = '%${q.replaceAll('%', '').replaceAll('_', '')}%';
      rows = await d.rawQuery(
          'SELECT p.*, $_qtyExpr AS qty FROM products p '
          'LEFT JOIN stock s ON s.product_id = p.id AND s.location_id = ? '
          'WHERE p.is_active = 1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode = ?) '
          'ORDER BY (p.barcode = ?) DESC, (p.sku = ?) DESC, p.name LIMIT ?',
          <Object?>[locationId, like, like, q, q, q, limit]);
    }
    return rows.map(Product.fromRow).toList();
  }

  /// Exactly one product for a scanned code, or null when the code is unknown.
  Future<Product?> byBarcode(String code, int locationId) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows = await d.rawQuery(
        'SELECT p.*, $_qtyExpr AS qty FROM products p '
        'LEFT JOIN stock s ON s.product_id = p.id AND s.location_id = ? '
        'WHERE p.is_active = 1 AND (p.barcode = ? OR p.sku = ?) LIMIT 2',
        <Object?>[locationId, code, code]);
    return rows.length == 1 ? Product.fromRow(rows.first) : null;
  }

  Future<Product?> byId(int id, int locationId) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows = await d.rawQuery(
        'SELECT p.*, $_qtyExpr AS qty FROM products p '
        'LEFT JOIN stock s ON s.product_id = p.id AND s.location_id = ? WHERE p.id = ? LIMIT 1',
        <Object?>[locationId, id]);
    return rows.isEmpty ? null : Product.fromRow(rows.first);
  }

  Future<List<Product>> lowStock(int locationId, {int limit = 100}) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows = await d.rawQuery(
        'SELECT p.*, $_qtyExpr AS qty FROM products p '
        'JOIN stock s ON s.product_id = p.id AND s.location_id = ? '
        'WHERE p.is_active = 1 AND s.low_level > 0 AND $_qtyExpr <= s.low_level '
        'ORDER BY qty ASC LIMIT ?',
        <Object?>[locationId, limit]);
    return rows.map(Product.fromRow).toList();
  }

  Future<List<Customer>> customers({String query = '', int limit = 50}) async {
    final Database d = await _db.db;
    final String like = '%${query.trim().replaceAll('%', '')}%';
    final List<Map<String, Object?>> rows = await d.query('customers',
        where: query.trim().isEmpty ? 'is_active = 1' : 'is_active = 1 AND (name LIKE ? OR phone LIKE ?)',
        whereArgs: query.trim().isEmpty ? null : <Object?>[like, like],
        orderBy: 'name',
        limit: limit);
    return rows.map(Customer.fromRow).toList();
  }

  Future<List<ShopLocation>> locations() async {
    final List<Map<String, Object?>> rows =
        await (await _db.db).query('locations', where: 'is_active = 1', orderBy: 'name');
    return rows.map(ShopLocation.fromRow).toList();
  }

  Future<int> productCount() async {
    final List<Map<String, Object?>> r =
        await (await _db.db).rawQuery('SELECT COUNT(*) AS n FROM products WHERE is_active = 1');
    return (r.first['n'] as int?) ?? 0;
  }
}

/// Sales this phone has made, whether or not the server has them yet.
class SalesDao {
  SalesDao(this._db);

  final LocalDb _db;

  Future<void> insert(LocalSale sale) async {
    final Database d = await _db.db;
    await d.transaction((Transaction t) async {
      await t.insert('local_sales', sale.toRow());
      for (final SaleLine line in sale.lines) {
        await t.insert('local_sale_items', line.toRow(sale.uuid));
      }
      for (final SalePayment pay in sale.payments) {
        await t.insert('local_sale_payments', pay.toRow(sale.uuid));
      }
      // Reserve the stock straight away so the next sale sees the lower number
      for (final SaleLine line in sale.lines) {
        if (line.productId == null) continue;
        await t.rawInsert(
            'INSERT OR REPLACE INTO stock_pending (uuid, product_id, location_id, delta) '
            'VALUES (?, ?, ?, COALESCE((SELECT delta FROM stock_pending WHERE uuid = ? AND product_id = ? AND location_id = ?), 0) + ?)',
            <Object?>[sale.uuid, line.productId, sale.locationId, sale.uuid, line.productId, sale.locationId, -line.qty]);
      }
    });
  }

  Future<List<LocalSale>> recent({int limit = 50}) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows =
        await d.query('local_sales', orderBy: 'created_at DESC', limit: limit);
    final List<LocalSale> out = <LocalSale>[];
    for (final Map<String, Object?> r in rows) {
      out.add(await _hydrate(d, r));
    }
    return out;
  }

  Future<LocalSale?> byUuid(String uuid) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> rows =
        await d.query('local_sales', where: 'uuid = ?', whereArgs: <Object?>[uuid], limit: 1);
    if (rows.isEmpty) return null;
    return _hydrate(d, rows.first);
  }

  Future<LocalSale> _hydrate(Database d, Map<String, Object?> row) async {
    final String uuid = row['uuid'] as String;
    final List<Map<String, Object?>> items =
        await d.query('local_sale_items', where: 'sale_uuid = ?', whereArgs: <Object?>[uuid]);
    final List<Map<String, Object?>> pays =
        await d.query('local_sale_payments', where: 'sale_uuid = ?', whereArgs: <Object?>[uuid]);
    return LocalSale.fromRow(row, items.map(SaleLine.fromRow).toList(), pays.map(SalePayment.fromRow).toList());
  }

  Future<SalesTotals> todayTotals() async {
    final Database d = await _db.db;
    final String today = DateTime.now().toIso8601String().substring(0, 10);
    final List<Map<String, Object?>> r = await d.rawQuery(
        "SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS total, COALESCE(SUM(paid),0) AS paid, "
        "COALESCE(SUM(balance),0) AS balance, "
        "COALESCE(SUM(CASE WHEN sync_status <> 'synced' THEN 1 ELSE 0 END),0) AS unsent "
        "FROM local_sales WHERE substr(created_at, 1, 10) = ?",
        <Object?>[today]);
    final Map<String, Object?> m = r.first;
    return SalesTotals(
      count: (m['n'] as int?) ?? 0,
      total: (m['total'] as num?)?.toDouble() ?? 0,
      paid: (m['paid'] as num?)?.toDouble() ?? 0,
      balance: (m['balance'] as num?)?.toDouble() ?? 0,
      unsent: (m['unsent'] as int?) ?? 0,
    );
  }

  /// Called when the server confirms a sale: the reservation becomes real stock.
  Future<void> markSynced(String uuid, Map<String, Object?> result) async {
    final Database d = await _db.db;
    await d.transaction((Transaction t) async {
      await t.update(
          'local_sales',
          <String, Object?>{
            'sync_status': 'synced',
            'server_doc_no': result['doc_no'] as String?,
            'server_id': result['sale_id'] as int?,
            'review_note': result['needs_review'] as String?,
            'error': null,
          },
          where: 'uuid = ?',
          whereArgs: <Object?>[uuid]);
      await t.delete('stock_pending', where: 'uuid = ?', whereArgs: <Object?>[uuid]);
    });
  }

  /// The server refused the sale. The reservation is released and the sale is
  /// kept on screen as rejected, so the cashier can see what happened.
  Future<void> markRejected(String uuid, String error) async {
    final Database d = await _db.db;
    await d.transaction((Transaction t) async {
      await t.update('local_sales', <String, Object?>{'sync_status': 'rejected', 'error': error},
          where: 'uuid = ?', whereArgs: <Object?>[uuid]);
      await t.delete('stock_pending', where: 'uuid = ?', whereArgs: <Object?>[uuid]);
    });
  }

  /// Next provisional number for a receipt printed before the server sees it.
  Future<String> nextClientRef(int deviceId) async {
    final int n = await _db.getMetaInt('client_seq') + 1;
    await _db.setMeta('client_seq', '$n');
    return 'D$deviceId-${n.toString().padLeft(6, '0')}';
  }
}

/// The queue of changes waiting to reach the server.
class OutboxDao {
  OutboxDao(this._db);

  final LocalDb _db;

  Future<void> add(String uuid, String kind, Map<String, Object?> payload) async {
    final Database d = await _db.db;
    final List<Map<String, Object?>> r = await d.rawQuery('SELECT COALESCE(MAX(seq), 0) + 1 AS s FROM outbox');
    await d.insert(
        'outbox',
        <String, Object?>{
          'uuid': uuid,
          'kind': kind,
          'payload': jsonEncode(payload),
          'created_at': DateTime.now().toIso8601String(),
          'status': 'pending',
          'seq': (r.first['s'] as int?) ?? 1,
        },
        conflictAlgorithm: ConflictAlgorithm.ignore);
  }

  /// Oldest first: the server applies them in the order they happened, so a
  /// customer created offline exists before the sale that refers to them.
  Future<List<OutboxEntry>> due({int limit = 50}) async {
    final List<Map<String, Object?>> rows = await (await _db.db).query('outbox',
        where: "status IN ('pending','failed')", orderBy: 'seq ASC', limit: limit);
    return rows.map(OutboxEntry.fromRow).toList();
  }

  Future<void> markApplied(String uuid, Map<String, Object?> result) async {
    await (await _db.db).update(
        'outbox',
        <String, Object?>{'status': 'applied', 'result': jsonEncode(result), 'last_error': null},
        where: 'uuid = ?',
        whereArgs: <Object?>[uuid]);
  }

  Future<void> markRejected(String uuid, String error) async {
    await (await _db.db).update('outbox', <String, Object?>{'status': 'rejected', 'last_error': error},
        where: 'uuid = ?', whereArgs: <Object?>[uuid]);
  }

  Future<void> markFailed(String uuid, String error) async {
    await (await _db.db).rawUpdate(
        "UPDATE outbox SET status = 'failed', attempts = attempts + 1, last_error = ? WHERE uuid = ?",
        <Object?>[error, uuid]);
  }

  Future<Map<String, Object?>?> resultFor(String uuid) async {
    final List<Map<String, Object?>> rows = await (await _db.db)
        .query('outbox', columns: <String>['result'], where: 'uuid = ?', whereArgs: <Object?>[uuid], limit: 1);
    if (rows.isEmpty || rows.first['result'] == null) return null;
    return jsonDecode(rows.first['result'] as String) as Map<String, Object?>;
  }

  /// Applied entries are kept briefly so a repeated push can still be matched,
  /// then cleared to keep the database small.
  Future<void> prune() async {
    final String cutoff = DateTime.now().subtract(const Duration(days: 14)).toIso8601String();
    await (await _db.db).delete('outbox',
        where: "status IN ('applied','rejected') AND created_at < ?", whereArgs: <Object?>[cutoff]);
  }
}

class OutboxEntry {
  OutboxEntry({required this.uuid, required this.kind, required this.payload, required this.createdAt, required this.attempts});

  factory OutboxEntry.fromRow(Map<String, Object?> r) => OutboxEntry(
        uuid: r['uuid'] as String,
        kind: r['kind'] as String,
        payload: jsonDecode(r['payload'] as String) as Map<String, Object?>,
        createdAt: r['created_at'] as String,
        attempts: (r['attempts'] as int?) ?? 0,
      );

  final String uuid;
  final String kind;
  final Map<String, Object?> payload;
  final String createdAt;
  final int attempts;

  Map<String, Object?> toWire() => <String, Object?>{
        'uuid': uuid,
        'kind': kind,
        'payload': payload,
        'created_at': createdAt,
      };
}

class SalesTotals {
  const SalesTotals({required this.count, required this.total, required this.paid, required this.balance, required this.unsent});

  final int count;
  final double total;
  final double paid;
  final double balance;
  final int unsent;
}
