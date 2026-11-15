import 'dart:async';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../domain/models/catalog.dart';
import '../../domain/models/sale.dart';
import '../../widgets/common.dart';
import '../../widgets/sync_banner.dart';
import '../sell/receipt_screen.dart';

/// Sales this phone has made, newest first, with whether the office has them.
class SalesScreen extends StatefulWidget {
  const SalesScreen({super.key});

  @override
  State<SalesScreen> createState() => _SalesScreenState();
}

class _SalesScreenState extends State<SalesScreen> {
  List<LocalSale> _sales = <LocalSale>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    final List<LocalSale> list = await AppScope.of(context).sales.recent(limit: 100);
    if (!mounted) return;
    setState(() {
      _sales = list;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);

    return Scaffold(
      appBar: AppBar(title: const Text('Sales')),
      body: Column(
        children: <Widget>[
          SyncBanner(sync: s.sync),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _sales.isEmpty
                    ? const EmptyState(
                        icon: Icons.receipt_long_outlined,
                        title: 'No sales yet',
                        message: 'Sales you make on this phone show here, whether or not they have reached the office.',
                      )
                    : RefreshIndicator(
                        onRefresh: () async {
                          await s.sync.syncNow(force: true);
                          await _load();
                        },
                        child: ListView.separated(
                          itemCount: _sales.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (BuildContext context, int i) {
                            final LocalSale sale = _sales[i];
                            return ListTile(
                              leading: _StatusDot(status: sale.syncStatus),
                              title: Text(sale.displayRef),
                              subtitle: Text(
                                '${_time(sale.createdAt)} · ${sale.lines.length} item(s)'
                                '${sale.customerName != null ? ' · ${sale.customerName}' : ''}',
                              ),
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: <Widget>[
                                  Text(money.formatDouble(sale.total),
                                      style: const TextStyle(fontWeight: FontWeight.w700)),
                                  if (sale.balance > 0)
                                    Text('${money.formatDouble(sale.balance)} owing',
                                        style: TextStyle(
                                            fontSize: 11, color: Theme.of(context).colorScheme.error)),
                                ],
                              ),
                              onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                                builder: (_) => ReceiptScreen(sale: sale, standalone: true),
                              )),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  static String _time(DateTime d) {
    String two(int v) => v.toString().padLeft(2, '0');
    final DateTime now = DateTime.now();
    final bool today = d.year == now.year && d.month == now.month && d.day == now.day;
    return today ? '${two(d.hour)}:${two(d.minute)}' : '${two(d.day)}/${two(d.month)} ${two(d.hour)}:${two(d.minute)}';
  }
}

class _StatusDot extends StatelessWidget {
  const _StatusDot({required this.status});

  final SyncStatus status;

  @override
  Widget build(BuildContext context) {
    final ColorScheme c = Theme.of(context).colorScheme;
    final (IconData icon, Color colour, String tip) = switch (status) {
      SyncStatus.synced => (Icons.cloud_done_outlined, c.primary, 'Recorded at the office'),
      SyncStatus.rejected => (Icons.error_outline, c.error, 'The office would not accept this'),
      SyncStatus.pending => (Icons.schedule, c.outline, 'Waiting to be sent'),
    };
    return Tooltip(message: tip, child: Icon(icon, color: colour));
  }
}
