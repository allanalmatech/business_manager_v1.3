import 'package:flutter/material.dart';

import '../../app.dart';
import '../../domain/models/catalog.dart';
import '../products/products_screen.dart';
import '../sales/sales_screen.dart';
import '../sell/sell_screen.dart';
import '../settings/settings_screen.dart';
import 'dashboard_screen.dart';

/// The app is four places: sell, look something up, see what has been sold,
/// and settings. Anything deeper is reached from one of those, so nobody has
/// to learn a menu.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final Session session = AppScope.of(context).session;
    final bool canSell = session.can('pos.create');

    final List<_Tab> tabs = <_Tab>[
      if (canSell) _Tab('Sell', Icons.point_of_sale_outlined, Icons.point_of_sale, const SellScreen()),
      _Tab('Today', Icons.insights_outlined, Icons.insights, const DashboardScreen()),
      _Tab('Products', Icons.inventory_2_outlined, Icons.inventory_2, const ProductsScreen()),
      _Tab('Sales', Icons.receipt_long_outlined, Icons.receipt_long, const SalesScreen()),
      _Tab('More', Icons.more_horiz_outlined, Icons.more_horiz, const SettingsScreen()),
    ];
    final int index = _index.clamp(0, tabs.length - 1);

    return Scaffold(
      // Keeps each tab's scroll position and the basket as you move about
      body: IndexedStack(index: index, children: tabs.map((_Tab t) => t.screen).toList()),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (int i) => setState(() => _index = i),
        destinations: tabs
            .map((_Tab t) => NavigationDestination(
                  icon: Icon(t.icon),
                  selectedIcon: Icon(t.activeIcon),
                  label: t.label,
                ))
            .toList(),
      ),
    );
  }
}

class _Tab {
  const _Tab(this.label, this.icon, this.activeIcon, this.screen);

  final String label;
  final IconData icon;
  final IconData activeIcon;
  final Widget screen;
}
