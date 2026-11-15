import '../../core/uuid.dart';
import '../../domain/models/catalog.dart';
import '../../domain/models/sale.dart';
import '../../domain/services/cart_service.dart';
import '../local/dao.dart';
import '../local/database.dart';

/// Recording a sale. It is written to the phone first and queued for the
/// server second, so the cashier is never kept waiting by the network and a
/// completed sale cannot be lost by closing the app.
class SalesRepository {
  SalesRepository({required LocalDb db, required SalesDao sales, required OutboxDao outbox})
      : _db = db,
        _sales = sales,
        _outbox = outbox;

  final LocalDb _db;
  final SalesDao _sales;
  final OutboxDao _outbox;

  Future<LocalSale> completeSale({
    required CartService cart,
    required int locationId,
    required Session session,
    String docType = 'receipt',
  }) async {
    final CartTotals t = cart.totals();
    final int d = session.decimals;
    final String uuid = newUuid();
    final String ref = await _sales.nextClientRef(session.deviceId);

    final LocalSale sale = LocalSale(
      uuid: uuid,
      clientRef: ref,
      locationId: locationId,
      lines: List<SaleLine>.from(cart.lines),
      payments: List<SalePayment>.from(cart.payments),
      customerId: cart.customer?.id,
      customerRef: cart.customerRef,
      customerName: cart.customer?.name,
      docType: docType,
      pricingMode: cart.wholesale ? 'wholesale' : 'retail',
      subtotal: t.subtotal.toServer(d),
      discount: t.discount.toServer(d),
      tax: t.tax.toServer(d),
      total: t.total.toServer(d),
      paid: t.paid.toServer(d),
      changeDue: t.changeDue.toServer(d),
      balance: t.balance.toServer(d),
      createdAt: DateTime.now(),
    );

    // Saved and queued together: a sale on screen is always a sale in the queue.
    await _sales.insert(sale);
    await _outbox.add(uuid, 'sale.create', sale.toWire());
    return sale;
  }

  /// A customer added at the till. The sale refers to this action's uuid, and
  /// the server swaps in the real id when it applies the batch.
  Future<Customer> addCustomerOffline({required String name, String? phone, String? email, String? address}) async {
    final String uuid = newUuid();
    await _outbox.add(uuid, 'customer.create', <String, Object?>{
      'name': name,
      'phone': phone ?? '',
      'email': email ?? '',
      'address': address ?? '',
    });
    await _db.setMeta('last_customer_ref', uuid);
    return Customer(id: -1, name: name, phone: phone, email: email, address: address);
  }

  Future<String?> get lastCustomerRef => _db.getMeta('last_customer_ref');

  Future<List<LocalSale>> recent({int limit = 50}) => _sales.recent(limit: limit);
  Future<SalesTotals> todayTotals() => _sales.todayTotals();
  Future<LocalSale?> byUuid(String uuid) => _sales.byUuid(uuid);
}

/// Changes to products and stock made from the phone. Both are expressed as
/// something that happened, not as a new total, so two phones working at once
/// cannot overwrite each other.
class CatalogRepository {
  CatalogRepository({required OutboxDao outbox, required CatalogDao dao}) : _outbox = outbox, _dao = dao;

  final OutboxDao _outbox;
  final CatalogDao _dao;

  CatalogDao get dao => _dao;

  /// `base` is what this phone last saw. If the server's value has moved on,
  /// it refuses rather than quietly overwriting someone else's change.
  Future<void> updateProduct({
    required int id,
    required Map<String, Object?> fields,
    required Map<String, Object?> base,
  }) async {
    await _outbox.add(newUuid(), 'product.update', <String, Object?>{
      'id': id,
      'fields': fields,
      'base': base,
    });
  }

  Future<void> adjustStock({
    required int productId,
    required int locationId,
    required double delta,
    required String reason,
    bool receiving = false,
  }) async {
    await _outbox.add(newUuid(), receiving ? 'stock.receive' : 'stock.adjust', <String, Object?>{
      'product_id': productId,
      'location_id': locationId,
      'delta': delta,
      'reason': reason,
    });
  }
}
