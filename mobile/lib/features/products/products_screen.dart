import 'dart:async';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../domain/models/catalog.dart';
import '../../widgets/common.dart';
import '../../widgets/product_image.dart';
import '../sell/scanner_screen.dart';
import 'product_detail_screen.dart';

/// Looking a product up: price, what is on the shelf, and its photo.
class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final TextEditingController _search = TextEditingController();
  Timer? _debounce;
  List<Product> _items = <Product>[];
  int _locationId = 0;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final AppServices s = AppScope.of(context);
    _locationId = await s.db.getMetaInt('location_id');
    if (_locationId == 0) {
      final List<ShopLocation> locs = await s.catalog.dao.locations();
      _locationId = locs.isNotEmpty ? locs.first.id : 0;
    }
    await _refresh();
  }

  Future<void> _refresh() async {
    final List<Product> items =
        await AppScope.of(context).catalog.dao.search(_search.text, _locationId, limit: 200);
    if (!mounted) return;
    setState(() {
      _items = items;
      _loading = false;
    });
  }

  Future<void> _scan() async {
    final String? code = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(builder: (_) => const ScannerScreen(title: 'Scan to look up')),
    );
    if (code == null || !mounted) return;
    _search.text = code;
    await _refresh();
    if (_items.length == 1 && mounted) await _open(_items.first);
  }

  Future<void> _open(Product p) async {
    await Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => ProductDetailScreen(product: p, locationId: _locationId),
    ));
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Products'),
        actions: <Widget>[
          IconButton(onPressed: _scan, icon: const Icon(Icons.qr_code_scanner), tooltip: 'Scan a barcode'),
        ],
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
            child: TextField(
              controller: _search,
              onChanged: (_) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 220), _refresh);
              },
              decoration: const InputDecoration(
                hintText: 'Search name, SKU or barcode',
                prefixIcon: Icon(Icons.search),
              ),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _items.isEmpty
                    ? const EmptyState(
                        icon: Icons.inventory_2_outlined,
                        title: 'Nothing found',
                        message: 'Products arrive on the phone when it syncs with the office.',
                      )
                    : RefreshIndicator(
                        onRefresh: () async {
                          await s.sync.syncNow(force: true);
                          await _refresh();
                        },
                        child: ListView.separated(
                          itemCount: _items.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (BuildContext context, int i) {
                            final Product p = _items[i];
                            return ListTile(
                              leading: ProductImage(cache: s.images, image: p.image, size: 48),
                              title: Text(p.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                              subtitle: Text(<String>[
                                if (p.sku != null && p.sku!.isNotEmpty) p.sku!,
                                '${formatQty(p.qty)} in stock',
                              ].join(' · ')),
                              trailing: Text(money.formatDouble(p.retailPrice),
                                  style: const TextStyle(fontWeight: FontWeight.w600)),
                              onTap: () => _open(p),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
