import 'dart:async';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../domain/models/catalog.dart';
import '../../domain/models/sale.dart';
import '../../widgets/common.dart';
import 'receipt_screen.dart';

/// Taking the money. Quick amounts for cash, because most sales are cash and
/// counting notes is the slow part.
class PaymentScreen extends StatefulWidget {
  const PaymentScreen({super.key, required this.locationId});

  final int locationId;

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  final TextEditingController _amount = TextEditingController();
  String _method = 'cash';
  bool _saving = false;

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);

    return ListenableBuilder(
      listenable: s.cart,
      builder: (BuildContext context, _) {
        final CartTotals t = s.cart.totals();
        final List<String> problems = s.cart.problems();

        return Scaffold(
          appBar: AppBar(title: const Text('Payment')),
          body: ListView(
            padding: const EdgeInsets.all(12),
            children: <Widget>[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: <Widget>[
                      Text('Amount due', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
                      const SizedBox(height: 4),
                      Text(money.format(t.total),
                          style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w800)),
                      if (t.paid.isPositive) ...<Widget>[
                        const Divider(height: 24),
                        _row('Paid so far', money.format(t.paid)),
                        if (t.balance.isPositive)
                          _row('Still owing', money.format(t.balance), color: Theme.of(context).colorScheme.error),
                        if (t.changeDue.isPositive)
                          _row('Change to give', money.format(t.changeDue), big: true),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              SegmentedButton<String>(
                segments: const <ButtonSegment<String>>[
                  ButtonSegment<String>(value: 'cash', label: Text('Cash'), icon: Icon(Icons.payments_outlined)),
                  ButtonSegment<String>(value: 'mobile_money', label: Text('Mobile'), icon: Icon(Icons.smartphone)),
                  ButtonSegment<String>(value: 'bank', label: Text('Bank'), icon: Icon(Icons.account_balance)),
                ],
                selected: <String>{_method},
                onSelectionChanged: (Set<String> v) => setState(() => _method = v.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'Amount received',
                  prefixText: session.currencySymbol,
                  suffixIcon: IconButton(
                    icon: const Icon(Icons.add_circle_outline),
                    tooltip: 'Add this payment',
                    onPressed: _addTyped,
                  ),
                ),
                onSubmitted: (_) => _addTyped(),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  ActionChip(
                    label: Text('Exact  ${money.format(t.balance)}'),
                    onPressed: t.balance.isZero ? null : () => _add(t.balance.toServer(session.decimals)),
                  ),
                  for (final int note in _notes(session.currencySymbol))
                    ActionChip(label: Text(MoneyFormat.grouped(note)), onPressed: () => _add(note.toDouble())),
                ],
              ),
              if (s.cart.payments.isNotEmpty) ...<Widget>[
                const SizedBox(height: 16),
                Card(
                  child: Column(
                    children: List<Widget>.generate(s.cart.payments.length, (int i) {
                      final SalePayment p = s.cart.payments[i];
                      return ListTile(
                        dense: true,
                        leading: Icon(switch (p.method) {
                          'mobile_money' => Icons.smartphone,
                          'bank' => Icons.account_balance,
                          _ => Icons.payments_outlined,
                        }),
                        title: Text(money.formatDouble(p.amount)),
                        subtitle: Text(switch (p.method) {
                          'mobile_money' => 'Mobile money',
                          'bank' => 'Bank',
                          _ => 'Cash',
                        }),
                        trailing: IconButton(
                          icon: const Icon(Icons.close),
                          tooltip: 'Remove',
                          onPressed: () => s.cart.removePayment(i),
                        ),
                      );
                    }),
                  ),
                ),
              ],
              if (problems.isNotEmpty) ...<Widget>[
                const SizedBox(height: 16),
                Card(
                  color: Theme.of(context).colorScheme.errorContainer,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: problems
                          .map((String p) => Padding(
                                padding: const EdgeInsets.symmetric(vertical: 2),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: <Widget>[
                                    const Icon(Icons.info_outline, size: 18),
                                    const SizedBox(width: 8),
                                    Expanded(child: Text(p)),
                                  ],
                                ),
                              ))
                          .toList(),
                    ),
                  ),
                ),
              ],
            ],
          ),
          bottomNavigationBar: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                onPressed: (_saving || problems.isNotEmpty) ? null : _complete,
                icon: _saving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.check_circle_outline),
                label: Text(t.balance.isPositive && t.paid.isPositive
                    ? 'Complete, ${money.format(t.balance)} on credit'
                    : (t.balance.isPositive ? 'Complete on credit' : 'Complete sale')),
              ),
            ),
          ),
        );
      },
    );
  }

  /// Round notes in common use, so a cashier taps rather than types.
  List<int> _notes(String currency) => const <int>[1000, 2000, 5000, 10000, 20000, 50000];

  void _addTyped() {
    final double? v = double.tryParse(_amount.text.trim());
    if (v == null || v <= 0) {
      showMessage(context, 'Enter an amount first.');
      return;
    }
    _add(v);
  }

  void _add(double amount) {
    AppScope.of(context).cart.addPayment(_method, amount);
    _amount.clear();
    FocusScope.of(context).unfocus();
  }

  Future<void> _complete() async {
    final AppServices s = AppScope.of(context);
    setState(() => _saving = true);

    // Written to the phone and queued in one step, so a sale on screen is
    // always a sale the office will eventually receive.
    final LocalSale sale = await s.sales.completeSale(
      cart: s.cart,
      locationId: widget.locationId,
      session: s.session,
    );
    s.cart.clear();
    await s.sync.refreshCounts();
    unawaited(s.sync.syncNow());

    if (!mounted) return;
    Navigator.of(context).pushReplacement(MaterialPageRoute<void>(
      builder: (_) => ReceiptScreen(sale: sale),
    ));
  }

  static Widget _row(String label, String value, {bool big = false, Color? color}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          children: <Widget>[
            Expanded(child: Text(label)),
            Text(value,
                style: TextStyle(
                  fontSize: big ? 20 : 15,
                  fontWeight: big ? FontWeight.w700 : FontWeight.w600,
                  color: color,
                )),
          ],
        ),
      );
}
