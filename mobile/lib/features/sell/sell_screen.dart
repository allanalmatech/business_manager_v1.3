import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../app.dart';
import '../../core/money.dart';
import '../../domain/models/catalog.dart';
import '../../widgets/common.dart';
import '../../widgets/product_image.dart';
import '../../widgets/sync_banner.dart';
import 'cart_screen.dart';
import 'scanner_screen.dart';

/// The till. Search or scan, tap to add, then check out.
/// Everything here reads from the phone's own database, so it is just as fast
/// with no connection as with one.
class SellScreen extends StatefulWidget {
  const SellScreen({super.key});

  @override
  State<SellScreen> createState() => _SellScreenState();
}

class _SellScreenState extends State<SellScreen> {
  final TextEditingController _search = TextEditingController();
  Timer? _debounce;
  List<Product> _results = <Product>[];
  List<ShopLocation> _locations = <ShopLocation>[];
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
    final List<ShopLocation> locs = await s.catalog.dao.locations();
    final int saved = await s.db.getMetaInt('location_id');
    if (!mounted) return;
    setState(() {
      _locations = locs;
      _locationId = locs.any((ShopLocation l) => l.id == saved) ? saved : (locs.isNotEmpty ? locs.first.id : 0);
      _loading = false;
    });
    await _refresh();
  }

  Future<void> _refresh() async {
    final AppServices s = AppScope.of(context);
    final List<Product> items = await s.catalog.dao.search(_search.text, _locationId);
    if (!mounted) return;
    setState(() => _results = items);
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 220), _refresh);
  }

  Future<void> _scan() async {
    final String? code = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(builder: (_) => const ScannerScreen()),
    );
    if (code == null || !mounted) return;
    await _addByCode(code);
  }

  /// A scanned code that matches one product goes straight into the basket.
  /// Anything else becomes a search, so the cashier can choose.
  Future<void> _addByCode(String code) async {
    final AppServices s = AppScope.of(context);
    final Product? p = await s.catalog.dao.byBarcode(code, _locationId);
    if (!mounted) return;
    if (p == null) {
      _search.text = code;
      await _refresh();
      if (mounted) showMessage(context, 'No product has that code. Showing what matches instead.');
      return;
    }
    _add(p);
  }

  void _add(Product p) {
    final AppServices s = AppScope.of(context);
    if (p.qty <= 0) {
      showMessage(context, '${p.name} shows none in stock. It can still be sold, and the office will see it.', error: false);
    }
    s.cart.addProduct(p);
    HapticFeedback.selectionClick();
  }

  Future<void> _openCart() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(
      builder: (_) => CartScreen(locationId: _locationId),
    ));
    if (mounted) await _refresh(); // stock changed if a sale went through
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Sell'),
        actions: <Widget>[
          if (_locations.length > 1)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: DropdownButton<int>(
                value: _locationId,
                underline: const SizedBox.shrink(),
                borderRadius: BorderRadius.circular(12),
                items: _locations
                    .map((ShopLocation l) => DropdownMenuItem<int>(value: l.id, child: Text(l.name)))
                    .toList(),
                onChanged: (int? v) async {
                  if (v == null) return;
                  setState(() => _locationId = v);
                  await s.db.setMeta('location_id', '$v');
                  await _refresh();
                },
              ),
            ),
        ],
      ),
      body: Column(
        children: <Widget>[
          SyncBanner(sync: s.sync),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: TextField(
                    controller: _search,
                    onChanged: _onSearchChanged,
                    onSubmitted: (String v) => v.trim().isEmpty ? null : _addByCode(v.trim()),
                    textInputAction: TextInputAction.search,
                    decoration: InputDecoration(
                      hintText: 'Search name, SKU or barcode',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: _search.text.isEmpty
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.close),
                              tooltip: 'Clear',
                              onPressed: () {
                                _search.clear();
                                _refresh();
                              },
                            ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  height: 52,
                  width: 52,
                  child: FilledButton(
                    onPressed: _scan,
                    style: FilledButton.styleFrom(padding: EdgeInsets.zero),
                    child: const Icon(Icons.qr_code_scanner),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _results.isEmpty
                    ? EmptyState(
                        icon: _search.text.isEmpty ? Icons.inventory_2_outlined : Icons.search_off,
                        title: _search.text.isEmpty ? 'No products yet' : 'Nothing matches "${_search.text}"',
                        message: _search.text.isEmpty
                            ? 'Products appear here once the phone has synced with the office.'
                            : 'Check the spelling, or scan the barcode.',
                      )
                    : RefreshIndicator(
                        onRefresh: () async {
                          await s.sync.syncNow(force: true);
                          await _refresh();
                        },
                        child: GridView.builder(
                          padding: const EdgeInsets.fromLTRB(12, 4, 12, 96),
                          gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                            maxCrossAxisExtent: 180, // 2 across on a small phone, more on a tablet
                            mainAxisExtent: 196,
                            crossAxisSpacing: 10,
                            mainAxisSpacing: 10,
                          ),
                          itemCount: _results.length,
                          itemBuilder: (BuildContext context, int i) =>
                              _ProductTile(product: _results[i], money: money, onTap: () => _add(_results[i])),
                        ),
                      ),
          ),
        ],
      ),
      bottomNavigationBar: ListenableBuilder(
        listenable: s.cart,
        builder: (BuildContext context, _) {
          if (s.cart.isEmpty) return const SizedBox.shrink();
          final ColorScheme c = Theme.of(context).colorScheme;
          return Material(
            color: c.primary,
            child: InkWell(
              onTap: _openCart,
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  child: Row(
                    children: <Widget>[
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          Text('${s.cart.itemCount} item(s)',
                              style: TextStyle(color: c.onPrimary.withOpacity(.85), fontSize: 12)),
                          Text(money.format(s.cart.totals().total),
                              style: TextStyle(color: c.onPrimary, fontSize: 20, fontWeight: FontWeight.w700)),
                        ],
                      ),
                      const Spacer(),
                      Text('View basket', style: TextStyle(color: c.onPrimary, fontWeight: FontWeight.w600)),
                      Icon(Icons.chevron_right, color: c.onPrimary),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({required this.product, required this.money, required this.onTap});

  final Product product;
  final MoneyFormat money;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final ColorScheme c = Theme.of(context).colorScheme;
    final bool out = product.qty <= 0;

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Center(child: ProductImage(cache: s.images, image: product.image, size: 92, radius: 12)),
              const SizedBox(height: 8),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, height: 1.2),
              ),
              const Spacer(),
              Text(money.formatDouble(product.priceFor(s.cart.wholesale)),
                  style: TextStyle(color: c.primary, fontWeight: FontWeight.w700, fontSize: 15)),
              Text(
                out ? 'None in stock' : '${formatQty(product.qty)} in stock',
                style: TextStyle(fontSize: 11, color: out ? c.error : c.onSurfaceVariant),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
