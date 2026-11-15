import 'package:flutter/foundation.dart';

import '../../core/money.dart';
import '../models/catalog.dart';
import '../models/sale.dart';

/// The basket on screen. Holds lines and payments and works out the totals the
/// same way the server does, so the printed receipt matches what gets recorded.
class CartService extends ChangeNotifier {
  CartService({required this.session});

  Session session;

  final List<SaleLine> lines = <SaleLine>[];
  final List<SalePayment> payments = <SalePayment>[];
  bool wholesale = false;
  Customer? customer;

  /// Set when the customer was added on this phone and has no server id yet.
  String? customerRef;
  String notes = '';

  bool get isEmpty => lines.isEmpty;
  int get itemCount => lines.length;

  void addProduct(Product p, {double qty = 1}) {
    final int i = lines.indexWhere((SaleLine l) => l.productId == p.id);
    if (i >= 0) {
      lines[i].qty += qty;
    } else {
      lines.add(SaleLine(
        productId: p.id,
        name: p.name,
        sku: p.sku,
        qty: qty,
        unitPrice: p.priceFor(wholesale),
        allowsFractions: p.allowsFractions,
        wholesaleFloor: p.wholesalePrice,
      ));
    }
    notifyListeners();
  }

  void setQty(int index, double qty) {
    if (index < 0 || index >= lines.length) return;
    if (qty <= 0) {
      lines.removeAt(index);
    } else {
      final SaleLine l = lines[index];
      l.qty = l.allowsFractions ? qty : qty.roundToDouble();
    }
    notifyListeners();
  }

  void setPrice(int index, double price) {
    if (index < 0 || index >= lines.length || price < 0) return;
    lines[index].unitPrice = price;
    notifyListeners();
  }

  void setDiscount(int index, double discount) {
    if (index < 0 || index >= lines.length || discount < 0) return;
    lines[index].discount = discount;
    notifyListeners();
  }

  void removeLine(int index) {
    if (index < 0 || index >= lines.length) return;
    lines.removeAt(index);
    notifyListeners();
  }

  /// Switching between retail and wholesale re-prices lines the cashier has not
  /// overridden, which is what a till is expected to do.
  void setWholesale(bool value, Map<int, Product> byId) {
    if (wholesale == value) return;
    wholesale = value;
    for (final SaleLine l in lines) {
      final Product? p = byId[l.productId];
      if (p == null) continue;
      if (l.unitPrice == p.priceFor(!value)) l.unitPrice = p.priceFor(value);
    }
    notifyListeners();
  }

  void setCustomer(Customer? c, {String? ref}) {
    customer = c;
    customerRef = ref;
    notifyListeners();
  }

  void addPayment(String method, double amount, {String? reference}) {
    if (amount <= 0) return;
    payments.add(SalePayment(method: method, amount: amount, reference: reference));
    notifyListeners();
  }

  void removePayment(int index) {
    if (index < 0 || index >= payments.length) return;
    payments.removeAt(index);
    notifyListeners();
  }

  void clear() {
    lines.clear();
    payments.clear();
    customer = null;
    customerRef = null;
    notes = '';
    notifyListeners();
  }

  /// Mirrors includes/tax.php: inclusive tax is carved out of the price,
  /// exclusive tax is added on top.
  CartTotals totals() {
    final int d = session.decimals;
    int subtotalMinor = 0;
    int discountMinor = 0;
    for (final SaleLine l in lines) {
      subtotalMinor += Money.fromServer(l.qty * l.unitPrice, decimals: d).minor;
      discountMinor += Money.fromServer(l.discount, decimals: d).minor;
    }
    final int netMinor = (subtotalMinor - discountMinor).clamp(0, 1 << 62);

    int taxMinor = 0;
    int totalMinor = netMinor;
    if (session.taxEnabled && session.taxRate > 0 && netMinor > 0) {
      final double r = session.taxRate / 100;
      if (session.taxInclusive) {
        taxMinor = (netMinor - netMinor / (1 + r)).round();
      } else {
        taxMinor = (netMinor * r).round();
        totalMinor = netMinor + taxMinor;
      }
    }
    int paidMinor = 0;
    for (final SalePayment p in payments) {
      paidMinor += Money.fromServer(p.amount, decimals: d).minor;
    }
    return CartTotals(
      subtotal: Money.fromMinor(subtotalMinor),
      discount: Money.fromMinor(discountMinor),
      tax: Money.fromMinor(taxMinor),
      total: Money.fromMinor(totalMinor),
      paid: Money.fromMinor(paidMinor),
    );
  }

  /// Reasons the sale cannot be completed, in words a cashier can act on.
  List<String> problems() {
    final List<String> out = <String>[];
    if (lines.isEmpty) out.add('The basket is empty.');
    for (final SaleLine l in lines) {
      if (l.qty <= 0) out.add('${l.name}: quantity must be more than zero.');
      if (!l.allowsFractions && l.qty != l.qty.roundToDouble()) {
        out.add('${l.name} is sold in whole pieces.');
      }
      if (l.wholesaleFloor > 0 && l.unitPrice < l.wholesaleFloor - 0.005) {
        out.add('${l.name} cannot be sold below the wholesale price.');
      }
      if (l.discount > l.qty * l.unitPrice) {
        out.add('${l.name}: the discount is more than the line total.');
      }
    }
    final CartTotals t = totals();
    if (t.balance.isPositive && customer == null && customerRef == null) {
      out.add('Choose a customer to sell on credit, or take full payment.');
    }
    if (t.balance.isPositive && !session.can('pos.allow_debt')) {
      out.add('You are not allowed to sell on credit.');
    }
    return out;
  }
}
