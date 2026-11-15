import 'dart:typed_data';

import '../../core/money.dart';
import '../models/catalog.dart';
import '../models/sale.dart';
import '../../platform/escpos.dart';

/// Turns a recorded sale into printer bytes. Kept apart from the screens so the
/// same receipt can be printed again later from the sales list.
class ReceiptBuilder {
  const ReceiptBuilder({required this.session});

  final Session session;

  Uint8List build(LocalSale sale, {bool openDrawer = false, String copyLabel = ''}) {
    final MoneyFormat f = MoneyFormat(symbol: '', decimals: session.decimals);
    final EscPos e = EscPos(columns: session.receiptWidth >= 80 ? 48 : 32);

    e.reset().align('center').bold(true).size(1);
    e.line(session.businessName);
    e.size(0).bold(false);
    if (session.businessAddress.isNotEmpty) e.line(session.businessAddress);
    if (session.businessPhone.isNotEmpty) e.line(session.businessPhone);
    if (session.receiptHeader.isNotEmpty) e.line(session.receiptHeader);
    if (copyLabel.isNotEmpty) e.line('($copyLabel)');
    e.align('left').rule();

    e.line(sale.displayRef);
    e.line(_when(sale.createdAt));
    e.line('Served by ${session.name.isEmpty ? session.username : session.name}');
    if (sale.customerName != null) e.line('Customer: ${sale.customerName}');
    if (sale.syncStatus != SyncStatus.synced) e.line('* not yet sent to the office *');
    e.rule();

    for (final SaleLine l in sale.lines) {
      e.item(l.name, '${formatQty(l.qty)} x ${f.formatDouble(l.unitPrice)}', f.formatDouble(l.lineTotal));
      if (l.discount > 0) e.row('  less discount', '-${f.formatDouble(l.discount)}');
    }

    e.rule();
    e.row('Subtotal', f.formatDouble(sale.subtotal));
    if (sale.discount > 0) e.row('Discount', '-${f.formatDouble(sale.discount)}');
    if (sale.tax > 0) {
      final String label = session.taxInclusive
          ? '${session.taxName} ${_rate()}% (included)'
          : '${session.taxName} ${_rate()}%';
      e.row(label, f.formatDouble(sale.tax));
    }
    e.size(1).row('TOTAL', f.formatDouble(sale.total), boldRow: true).size(0);

    for (final SalePayment p in sale.payments) {
      e.row(_method(p.method), f.formatDouble(p.amount));
    }
    if (sale.changeDue > 0) e.row('Change', f.formatDouble(sale.changeDue));
    if (sale.balance > 0) e.row('Balance owing', f.formatDouble(sale.balance), boldRow: true);

    e.rule();
    e.align('center');
    if (session.receiptFooter.isNotEmpty) e.line(session.receiptFooter);
    e.line(sale.displayRef);
    e.feed(1);
    if (openDrawer) e.openDrawer();
    e.cut();
    return e.bytes();
  }

  String _rate() {
    final double r = session.taxRate;
    return r == r.roundToDouble() ? r.round().toString() : r.toStringAsFixed(2);
  }

  static String _method(String m) => switch (m) {
        'mobile_money' => 'Mobile money',
        'bank' => 'Bank',
        _ => 'Cash',
      };

  static String _when(DateTime d) {
    String two(int v) => v.toString().padLeft(2, '0');
    return '${two(d.day)}/${two(d.month)}/${d.year}  ${two(d.hour)}:${two(d.minute)}';
  }
}
