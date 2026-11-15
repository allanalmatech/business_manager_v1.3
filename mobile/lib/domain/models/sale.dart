import '../../core/money.dart';

/// One line in the basket.
class SaleLine {
  SaleLine({
    required this.productId,
    required this.name,
    this.sku,
    required this.qty,
    required this.unitPrice,
    this.discount = 0,
    this.allowsFractions = false,
    this.wholesaleFloor = 0,
  });

  factory SaleLine.fromRow(Map<String, Object?> r) => SaleLine(
        productId: r['product_id'] as int?,
        name: (r['name'] as String?) ?? '',
        sku: r['sku'] as String?,
        qty: (r['qty'] as num?)?.toDouble() ?? 0,
        unitPrice: (r['unit_price'] as num?)?.toDouble() ?? 0,
        discount: (r['discount'] as num?)?.toDouble() ?? 0,
      );

  final int? productId;
  final String name;
  final String? sku;
  double qty;
  double unitPrice;
  double discount;
  final bool allowsFractions;
  final double wholesaleFloor;

  double get lineTotal => (qty * unitPrice) - discount;

  Map<String, Object?> toRow(String saleUuid) => <String, Object?>{
        'sale_uuid': saleUuid,
        'product_id': productId,
        'name': name,
        'sku': sku,
        'qty': qty,
        'unit_price': unitPrice,
        'discount': discount,
        'line_total': lineTotal,
      };

  /// What the server's sale endpoint expects.
  Map<String, Object?> toWire() => <String, Object?>{
        'product_id': productId,
        'qty': qty,
        'unit_price': unitPrice,
        'discount': discount,
      };
}

class SalePayment {
  const SalePayment({required this.method, required this.amount, this.reference});

  factory SalePayment.fromRow(Map<String, Object?> r) => SalePayment(
        method: (r['method'] as String?) ?? 'cash',
        amount: (r['amount'] as num?)?.toDouble() ?? 0,
        reference: r['reference'] as String?,
      );

  final String method; // cash | mobile_money | bank
  final double amount;
  final String? reference;

  Map<String, Object?> toRow(String saleUuid) =>
      <String, Object?>{'sale_uuid': saleUuid, 'method': method, 'amount': amount, 'reference': reference};

  Map<String, Object?> toWire() =>
      <String, Object?>{'method': method, 'amount': amount, 'reference': reference ?? ''};
}

enum SyncStatus { pending, synced, rejected }

/// A sale as this phone recorded it. It exists whether or not the server has
/// seen it, which is what lets a cashier keep selling with no network.
class LocalSale {
  LocalSale({
    required this.uuid,
    required this.clientRef,
    required this.locationId,
    required this.lines,
    required this.payments,
    this.customerId,
    this.customerRef,
    this.customerName,
    this.docType = 'receipt',
    this.pricingMode = 'retail',
    required this.subtotal,
    required this.discount,
    required this.tax,
    required this.total,
    required this.paid,
    required this.changeDue,
    required this.balance,
    required this.createdAt,
    this.syncStatus = SyncStatus.pending,
    this.serverDocNo,
    this.reviewNote,
    this.error,
  });

  factory LocalSale.fromRow(Map<String, Object?> r, List<SaleLine> lines, List<SalePayment> payments) => LocalSale(
        uuid: r['uuid'] as String,
        clientRef: (r['client_ref'] as String?) ?? '',
        locationId: (r['location_id'] as int?) ?? 0,
        lines: lines,
        payments: payments,
        customerId: r['customer_id'] as int?,
        customerRef: r['customer_ref'] as String?,
        customerName: r['customer_name'] as String?,
        docType: (r['doc_type'] as String?) ?? 'receipt',
        pricingMode: (r['pricing_mode'] as String?) ?? 'retail',
        subtotal: (r['subtotal'] as num?)?.toDouble() ?? 0,
        discount: (r['discount'] as num?)?.toDouble() ?? 0,
        tax: (r['tax'] as num?)?.toDouble() ?? 0,
        total: (r['total'] as num?)?.toDouble() ?? 0,
        paid: (r['paid'] as num?)?.toDouble() ?? 0,
        changeDue: (r['change_due'] as num?)?.toDouble() ?? 0,
        balance: (r['balance'] as num?)?.toDouble() ?? 0,
        createdAt: DateTime.parse(r['created_at'] as String),
        syncStatus: switch (r['sync_status'] as String?) {
          'synced' => SyncStatus.synced,
          'rejected' => SyncStatus.rejected,
          _ => SyncStatus.pending,
        },
        serverDocNo: r['server_doc_no'] as String?,
        reviewNote: r['review_note'] as String?,
        error: r['error'] as String?,
      );

  final String uuid;
  final String clientRef;
  final int locationId;
  final List<SaleLine> lines;
  final List<SalePayment> payments;
  final int? customerId;

  /// Set when the customer was created on this phone in the same batch; the
  /// server resolves it to the real id when the queue is pushed.
  final String? customerRef;
  final String? customerName;
  final String docType;
  final String pricingMode;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final double paid;
  final double changeDue;
  final double balance;
  final DateTime createdAt;
  final SyncStatus syncStatus;
  final String? serverDocNo;
  final String? reviewNote;
  final String? error;

  /// The official number once the server has it, otherwise this phone's own.
  String get displayRef => serverDocNo ?? clientRef;
  bool get isCredit => balance > 0.005;

  Map<String, Object?> toRow() => <String, Object?>{
        'uuid': uuid,
        'client_ref': clientRef,
        'location_id': locationId,
        'customer_id': customerId,
        'customer_ref': customerRef,
        'customer_name': customerName,
        'doc_type': docType,
        'pricing_mode': pricingMode,
        'subtotal': subtotal,
        'discount': discount,
        'tax': tax,
        'total': total,
        'paid': paid,
        'change_due': changeDue,
        'balance': balance,
        'created_at': createdAt.toIso8601String(),
        'sync_status': syncStatus.name,
        'server_doc_no': serverDocNo,
        'review_note': reviewNote,
        'error': error,
      };

  /// The payload the server's sale endpoint expects.
  Map<String, Object?> toWire() => <String, Object?>{
        'client_ref': clientRef,
        'selling_location_id': locationId,
        'doc_type': docType,
        'pricing_mode': pricingMode,
        if (customerId != null) 'customer_id': customerId,
        if (customerId == null && customerRef != null) 'customer_ref': customerRef,
        'sold_at': createdAt.toIso8601String().replaceFirst('T', ' ').substring(0, 19),
        'items': lines.map((SaleLine l) => l.toWire()).toList(),
        'payments': payments.map((SalePayment p) => p.toWire()).toList(),
      };
}

/// Works out a basket's totals exactly as includes/tax.php does on the server,
/// so the figure on the phone matches the receipt the server records.
class CartTotals {
  const CartTotals({required this.subtotal, required this.discount, required this.tax, required this.total, required this.paid});

  final Money subtotal;
  final Money discount;
  final Money tax;
  final Money total;
  final Money paid;

  Money get balance => Money.fromMinor((total.minor - paid.minor).clamp(0, 1 << 62));
  Money get changeDue => Money.fromMinor((paid.minor - total.minor).clamp(0, 1 << 62));
}
