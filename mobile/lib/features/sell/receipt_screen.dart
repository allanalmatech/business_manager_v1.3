import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../core/result.dart';
import '../../domain/models/catalog.dart';
import '../../domain/models/sale.dart';
import '../../domain/services/receipt_builder.dart';
import '../../widgets/common.dart';
import '../settings/printers_screen.dart';

/// Shown the moment a sale is done: the change to give, and the receipt.
/// Printing is optional — a sale is finished whether or not paper comes out.
class ReceiptScreen extends StatefulWidget {
  const ReceiptScreen({super.key, required this.sale, this.standalone = false});

  final LocalSale sale;

  /// True when opened from the sales list rather than straight after a sale.
  final bool standalone;

  @override
  State<ReceiptScreen> createState() => _ReceiptScreenState();
}

class _ReceiptScreenState extends State<ReceiptScreen> {
  bool _printing = false;

  Future<void> _print({bool openDrawer = false, String copyLabel = ''}) async {
    final AppServices s = AppScope.of(context);
    final String? address = await s.db.getMeta('printer_address');
    if (address == null || address.isEmpty) {
      if (!mounted) return;
      final bool go = await confirm(context, 'No printer chosen',
          'Pick a Bluetooth printer first. You can do that now, or skip printing.',
          yes: 'Choose printer');
      if (go && mounted) {
        await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const PrintersScreen()));
      }
      return;
    }

    setState(() => _printing = true);
    final Uint8List bytes = ReceiptBuilder(session: s.session)
        .build(widget.sale, openDrawer: openDrawer, copyLabel: copyLabel);
    final Result<void> res = await s.printer.print(address, bytes);
    if (!mounted) return;
    setState(() => _printing = false);
    if (res is Err<void>) {
      showMessage(context, res.message, error: true);
    } else {
      showMessage(context, 'Sent to the printer.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);
    final LocalSale sale = widget.sale;
    final ColorScheme c = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.standalone ? sale.displayRef : 'Sale complete'),
        automaticallyImplyLeading: widget.standalone,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          if (!widget.standalone)
            Column(
              children: <Widget>[
                Icon(Icons.check_circle, size: 64, color: c.primary),
                const SizedBox(height: 8),
                Text(money.formatDouble(sale.total),
                    style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800)),
                Text(sale.displayRef, style: TextStyle(color: c.onSurfaceVariant)),
                const SizedBox(height: 16),
              ],
            ),
          if (sale.changeDue > 0)
            Card(
              color: c.primaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  children: <Widget>[
                    Text('Change to give', style: TextStyle(color: c.onPrimaryContainer)),
                    Text(money.formatDouble(sale.changeDue),
                        style: TextStyle(fontSize: 40, fontWeight: FontWeight.w800, color: c.onPrimaryContainer)),
                  ],
                ),
              ),
            ),
          if (sale.balance > 0)
            Card(
              color: c.errorContainer,
              child: ListTile(
                leading: Icon(Icons.schedule, color: c.onErrorContainer),
                title: Text('${money.formatDouble(sale.balance)} on credit',
                    style: TextStyle(color: c.onErrorContainer, fontWeight: FontWeight.w600)),
                subtitle: Text(sale.customerName ?? 'No customer recorded',
                    style: TextStyle(color: c.onErrorContainer)),
              ),
            ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  for (final SaleLine l in sale.lines)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(
                        children: <Widget>[
                          Expanded(child: Text('${l.name}\n${formatQty(l.qty)} x ${money.formatDouble(l.unitPrice)}')),
                          Text(money.formatDouble(l.lineTotal), style: const TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  const Divider(),
                  if (sale.tax > 0)
                    Row(children: <Widget>[
                      Expanded(child: Text(session.taxName)),
                      Text(money.formatDouble(sale.tax)),
                    ]),
                  Row(children: <Widget>[
                    const Expanded(child: Text('Total', style: TextStyle(fontWeight: FontWeight.w700))),
                    Text(money.formatDouble(sale.total), style: const TextStyle(fontWeight: FontWeight.w700)),
                  ]),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _SyncNote(sale: sale),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _printing ? null : () => _print(openDrawer: true),
                  icon: _printing
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.print_outlined),
                  label: const Text('Print'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _printing ? null : () => _print(copyLabel: 'Customer copy'),
                  icon: const Icon(Icons.copy_all_outlined),
                  label: const Text('Copy'),
                ),
              ),
            ],
          ),
        ],
      ),
      bottomNavigationBar: widget.standalone
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: FilledButton.icon(
                  onPressed: () => Navigator.of(context).popUntil((Route<void> r) => r.isFirst),
                  icon: const Icon(Icons.add_shopping_cart),
                  label: const Text('Next sale'),
                ),
              ),
            ),
    );
  }
}

class _SyncNote extends StatelessWidget {
  const _SyncNote({required this.sale});

  final LocalSale sale;

  @override
  Widget build(BuildContext context) {
    final ColorScheme c = Theme.of(context).colorScheme;
    final (IconData icon, String text, Color colour) = switch (sale.syncStatus) {
      SyncStatus.synced => (Icons.cloud_done_outlined, 'Recorded at the office as ${sale.serverDocNo ?? ''}.', c.primary),
      SyncStatus.rejected => (Icons.error_outline, sale.error ?? 'The office would not accept this sale.', c.error),
      SyncStatus.pending => (Icons.schedule, 'Saved on this phone. It will reach the office when there is a connection.', c.onSurfaceVariant),
    };
    return Row(
      children: <Widget>[
        Icon(icon, size: 18, color: colour),
        const SizedBox(width: 8),
        Expanded(child: Text(text, style: TextStyle(fontSize: 12, color: colour))),
      ],
    );
  }
}
