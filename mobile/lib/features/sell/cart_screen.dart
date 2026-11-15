import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../domain/models/catalog.dart';
import '../../domain/models/sale.dart';
import '../../widgets/common.dart';
import 'payment_screen.dart';

/// The basket: change quantities and prices, pick a customer, then take payment.
class CartScreen extends StatefulWidget {
  const CartScreen({super.key, required this.locationId});

  final int locationId;

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);

    return ListenableBuilder(
      listenable: s.cart,
      builder: (BuildContext context, _) {
        final CartTotals t = s.cart.totals();
        if (s.cart.isEmpty) {
          // Emptying the last line takes you back to the products
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted && Navigator.of(context).canPop()) Navigator.of(context).pop();
          });
        }
        return Scaffold(
          appBar: AppBar(
            title: const Text('Basket'),
            actions: <Widget>[
              if (session.can('pos.edit_price'))
                TextButton(
                  onPressed: () => _togglePricing(s),
                  child: Text(s.cart.wholesale ? 'Wholesale' : 'Retail'),
                ),
              IconButton(
                tooltip: 'Empty the basket',
                icon: const Icon(Icons.delete_outline),
                onPressed: () async {
                  if (await confirm(context, 'Empty the basket?', 'Everything in it will be removed.',
                      yes: 'Empty', danger: true)) {
                    s.cart.clear();
                  }
                },
              ),
            ],
          ),
          body: ListView(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
            children: <Widget>[
              ...List<Widget>.generate(s.cart.lines.length, (int i) {
                final SaleLine l = s.cart.lines[i];
                return Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _LineCard(
                    line: l,
                    money: money,
                    canEditPrice: session.can('pos.edit_price'),
                    canDiscount: session.can('pos.apply_discount'),
                    onQty: (double q) => s.cart.setQty(i, q),
                    onPrice: (double p) => s.cart.setPrice(i, p),
                    onDiscount: (double d) => s.cart.setDiscount(i, d),
                    onRemove: () => s.cart.removeLine(i),
                  ),
                );
              }),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.person_outline),
                  title: Text(s.cart.customer?.name ?? 'Walk-in customer'),
                  subtitle: Text(s.cart.customer?.phone ?? 'Tap to choose or add a customer'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _pickCustomer(s),
                ),
              ),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: <Widget>[
                      _row('Subtotal', money.format(t.subtotal)),
                      if (t.discount.isPositive) _row('Discount', '-${money.format(t.discount)}'),
                      if (t.tax.isPositive)
                        _row(
                          session.taxInclusive
                              ? '${session.taxName} (included)'
                              : session.taxName,
                          money.format(t.tax),
                        ),
                      const Divider(height: 20),
                      _row('Total', money.format(t.total), big: true),
                    ],
                  ),
                ),
              ),
            ],
          ),
          bottomNavigationBar: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                onPressed: s.cart.isEmpty
                    ? null
                    : () => Navigator.of(context).push(MaterialPageRoute<void>(
                          builder: (_) => PaymentScreen(locationId: widget.locationId),
                        )),
                icon: const Icon(Icons.payments_outlined),
                label: Text('Take payment  ${money.format(t.total)}'),
              ),
            ),
          ),
        );
      },
    );
  }

  Future<void> _togglePricing(AppServices s) async {
    final Map<int, Product> byId = <int, Product>{};
    for (final SaleLine l in s.cart.lines) {
      if (l.productId == null) continue;
      final Product? p = await s.catalog.dao.byId(l.productId!, widget.locationId);
      if (p != null) byId[p.id] = p;
    }
    s.cart.setWholesale(!s.cart.wholesale, byId);
  }

  Future<void> _pickCustomer(AppServices s) async {
    final Customer? picked = await showModalBottomSheet<Customer>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _CustomerPicker(services: s),
    );
    if (picked == null) return;
    if (picked.id == -1) {
      // Added on this phone: the sale points at the queued action until the
      // office gives the customer a real number.
      s.cart.setCustomer(picked, ref: await s.sales.lastCustomerRef);
    } else {
      s.cart.setCustomer(picked);
    }
  }

  static Widget _row(String label, String value, {bool big = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          children: <Widget>[
            Expanded(child: Text(label, style: TextStyle(fontSize: big ? 16 : 14, fontWeight: big ? FontWeight.w700 : null))),
            Text(value, style: TextStyle(fontSize: big ? 20 : 14, fontWeight: big ? FontWeight.w700 : FontWeight.w500)),
          ],
        ),
      );
}

class _LineCard extends StatelessWidget {
  const _LineCard({
    required this.line,
    required this.money,
    required this.canEditPrice,
    required this.canDiscount,
    required this.onQty,
    required this.onPrice,
    required this.onDiscount,
    required this.onRemove,
  });

  final SaleLine line;
  final MoneyFormat money;
  final bool canEditPrice;
  final bool canDiscount;
  final ValueChanged<double> onQty;
  final ValueChanged<double> onPrice;
  final ValueChanged<double> onDiscount;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final ColorScheme c = Theme.of(context).colorScheme;
    return Dismissible(
      key: ValueKey<String>('line-${line.productId}-${line.name}'),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        decoration: BoxDecoration(color: c.errorContainer, borderRadius: BorderRadius.circular(16)),
        child: Icon(Icons.delete_outline, color: c.onErrorContainer),
      ),
      onDismissed: (_) => onRemove(),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
          child: Column(
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(line.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                        const SizedBox(height: 2),
                        InkWell(
                          onTap: canEditPrice ? () => _editNumber(context, 'Price', line.unitPrice, onPrice) : null,
                          child: Text(
                            '${money.formatDouble(line.unitPrice)} each${canEditPrice ? '  ✎' : ''}',
                            style: TextStyle(fontSize: 12, color: c.onSurfaceVariant),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(money.formatDouble(line.lineTotal),
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: <Widget>[
                  _StepButton(icon: Icons.remove, onTap: () => onQty(line.qty - 1)),
                  InkWell(
                    onTap: () => _editNumber(context, 'Quantity', line.qty, onQty, allowFractions: line.allowsFractions),
                    child: Container(
                      constraints: const BoxConstraints(minWidth: 64),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      alignment: Alignment.center,
                      child: Text(formatQty(line.qty),
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                    ),
                  ),
                  _StepButton(icon: Icons.add, onTap: () => onQty(line.qty + 1)),
                  const Spacer(),
                  if (canDiscount)
                    TextButton.icon(
                      onPressed: () => _editNumber(context, 'Discount on this line', line.discount, onDiscount),
                      icon: const Icon(Icons.local_offer_outlined, size: 16),
                      label: Text(line.discount > 0 ? money.formatDouble(line.discount) : 'Discount'),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _editNumber(BuildContext context, String title, double current, ValueChanged<double> onDone,
      {bool allowFractions = true}) async {
    final TextEditingController controller = TextEditingController(text: formatQty(current));
    final double? value = await showDialog<double>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: TextInputType.numberWithOptions(decimal: allowFractions),
          decoration: const InputDecoration(border: OutlineInputBorder()),
          onSubmitted: (String v) => Navigator.pop(context, double.tryParse(v)),
        ),
        actions: <Widget>[
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(context, double.tryParse(controller.text)),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (value != null && value >= 0) onDone(value);
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkResponse(
      onTap: onTap,
      radius: 24,
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, size: 20),
      ),
    );
  }
}

/// Choose an existing customer, or add one without leaving the sale.
class _CustomerPicker extends StatefulWidget {
  const _CustomerPicker({required this.services});

  final AppServices services;

  @override
  State<_CustomerPicker> createState() => _CustomerPickerState();
}

class _CustomerPickerState extends State<_CustomerPicker> {
  final TextEditingController _query = TextEditingController();
  List<Customer> _found = <Customer>[];

  @override
  void initState() {
    super.initState();
    _search();
  }

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    final List<Customer> list = await widget.services.catalog.dao.customers(query: _query.text);
    if (mounted) setState(() => _found = list);
  }

  Future<void> _addNew() async {
    final TextEditingController name = TextEditingController(text: _query.text);
    final TextEditingController phone = TextEditingController();
    final bool? ok = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('New customer'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            TextField(controller: name, autofocus: true, decoration: const InputDecoration(labelText: 'Name')),
            const SizedBox(height: 10),
            TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone (optional)')),
          ],
        ),
        actions: <Widget>[
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Add')),
        ],
      ),
    );
    if (ok == true && name.text.trim().isNotEmpty && mounted) {
      final Customer c = await widget.services.sales
          .addCustomerOffline(name: name.text.trim(), phone: phone.text.trim());
      if (mounted) Navigator.pop(context, c);
    }
    name.dispose();
    phone.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * .7,
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: TextField(
                controller: _query,
                onChanged: (_) => _search(),
                decoration: const InputDecoration(hintText: 'Search customers', prefixIcon: Icon(Icons.search)),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.person_off_outlined),
              title: const Text('Walk-in customer'),
              onTap: () => Navigator.pop(context, null),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView.builder(
                itemCount: _found.length,
                itemBuilder: (BuildContext context, int i) => ListTile(
                  leading: const Icon(Icons.person_outline),
                  title: Text(_found[i].name),
                  subtitle: _found[i].phone == null ? null : Text(_found[i].phone!),
                  onTap: () => Navigator.pop(context, _found[i]),
                ),
              ),
            ),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: OutlinedButton.icon(
                  onPressed: _addNew,
                  icon: const Icon(Icons.person_add_alt),
                  label: const Text('Add a new customer'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
