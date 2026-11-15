import 'dart:async';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../data/local/dao.dart';
import '../../domain/models/catalog.dart';
import '../../widgets/common.dart';
import '../../widgets/sync_banner.dart';

/// What happened today, from this phone's own records, so it is right even
/// with no connection. Figures cover this device unless the person has
/// permission to see the whole shop's reports.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  SalesTotals? _totals;
  List<Product> _low = <Product>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    final AppServices s = AppScope.of(context);
    final int locationId = await s.db.getMetaInt('location_id');
    final SalesTotals totals = await s.sales.todayTotals();
    final List<Product> low = s.session.can('products.view')
        ? await s.catalog.dao.lowStock(locationId, limit: 20)
        : <Product>[];
    if (!mounted) return;
    setState(() {
      _totals = totals;
      _low = low;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);
    final SalesTotals? t = _totals;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Today'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Sync now',
            icon: const Icon(Icons.sync),
            onPressed: () async {
              await s.sync.syncNow(force: true);
              await _load();
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await s.sync.syncNow(force: true);
          await _load();
        },
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 24),
                children: <Widget>[
                  SyncBanner(sync: s.sync),
                  const SizedBox(height: 12),
                  Text('Hello, ${session.name.isEmpty ? session.username : session.name}',
                      style: Theme.of(context).textTheme.titleMedium),
                  Text(session.businessName,
                      style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
                  const SizedBox(height: 12),
                  GridView.count(
                    crossAxisCount: MediaQuery.of(context).size.width > 520 ? 4 : 2,
                    childAspectRatio: 1.55,
                    crossAxisSpacing: 10,
                    mainAxisSpacing: 10,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    children: <Widget>[
                      StatTile(
                        label: 'Sales today',
                        value: money.formatDouble(t?.total ?? 0),
                        sub: '${t?.count ?? 0} sale(s)',
                        icon: Icons.receipt_long_outlined,
                      ),
                      StatTile(
                        label: 'Taken',
                        value: money.formatDouble(t?.paid ?? 0),
                        sub: 'Payments received',
                        icon: Icons.payments_outlined,
                      ),
                      StatTile(
                        label: 'On credit',
                        value: money.formatDouble(t?.balance ?? 0),
                        sub: 'Still owed',
                        icon: Icons.schedule,
                      ),
                      StatTile(
                        label: 'Waiting to send',
                        value: '${t?.unsent ?? 0}',
                        sub: (t?.unsent ?? 0) == 0 ? 'All sent' : 'Tap to send now',
                        icon: Icons.cloud_upload_outlined,
                        onTap: () => s.sync.syncNow(force: true),
                      ),
                    ],
                  ),
                  if (_low.isNotEmpty) ...<Widget>[
                    const SizedBox(height: 16),
                    Text('Running low', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    Card(
                      child: Column(
                        children: _low
                            .take(8)
                            .map((Product p) => ListTile(
                                  dense: true,
                                  leading: const Icon(Icons.warning_amber_outlined),
                                  title: Text(p.name),
                                  trailing: Text('${formatQty(p.qty)} left',
                                      style: TextStyle(color: Theme.of(context).colorScheme.error)),
                                ))
                            .toList(),
                      ),
                    ),
                  ],
                ],
              ),
      ),
    );
  }
}
