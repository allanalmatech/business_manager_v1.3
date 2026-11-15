import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../app.dart';
import '../../core/config.dart';
import '../../core/money.dart';
import '../../core/result.dart';
import '../../domain/models/catalog.dart';
import '../../widgets/common.dart';
import '../../widgets/product_image.dart';

/// One product: its photo, its prices, and what is on the shelf.
/// Changes are queued like everything else, so they work with no connection.
class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({super.key, required this.product, required this.locationId});

  final Product product;
  final int locationId;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late Product _product = widget.product;
  bool _busy = false;

  Future<void> _reload() async {
    final Product? fresh =
        await AppScope.of(context).catalog.dao.byId(_product.id, widget.locationId);
    if (fresh != null && mounted) setState(() => _product = fresh);
  }

  /// Photographs the product, shrinks it on the phone, then uploads.
  ///
  /// A phone camera makes a 3–5 MB picture; sending that over a mobile
  /// connection is slow and expensive, and the server only ever displays it
  /// small. Shrinking first turns it into a couple of hundred kilobytes.
  Future<void> _takePhoto() async {
    final AppServices s = AppScope.of(context);
    final ImagePicker picker = ImagePicker();
    final XFile? shot = await picker.pickImage(
      source: ImageSource.camera,
      maxWidth: AppConfig.photoMaxEdge.toDouble(),
      maxHeight: AppConfig.photoMaxEdge.toDouble(),
      imageQuality: 88,
    );
    if (shot == null || !mounted) return;

    setState(() => _busy = true);
    try {
      final File? small = await _compress(File(shot.path));
      if (small == null) {
        if (mounted) showMessage(context, 'That photo could not be prepared.', error: true);
        return;
      }
      final Result<Map<String, Object?>> res = await s.api.upload(
        'products/image',
        <String, String>{'product_id': '${_product.id}'},
        'image',
        small.path,
      );
      if (!mounted) return;
      if (res is Err<Map<String, Object?>>) {
        showMessage(
          context,
          res.offline
              ? 'No connection. Take the photo again when the phone is online.'
              : res.message,
          error: true,
        );
        return;
      }
      // The office now has it; pull so this phone shows the same picture.
      await s.sync.syncNow(force: true);
      await _reload();
      if (mounted) showMessage(context, 'Photo saved.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Steps the quality down until the file is small enough to send cheaply.
  Future<File?> _compress(File source) async {
    final Directory dir = await getTemporaryDirectory();
    for (final int quality in <int>[80, 65, 50, 35]) {
      final String target = p.join(dir.path, 'upload_${DateTime.now().microsecondsSinceEpoch}_$quality.jpg');
      final XFile? out = await FlutterImageCompress.compressAndGetFile(
        source.absolute.path,
        target,
        quality: quality,
        minWidth: AppConfig.photoMaxEdge,
        minHeight: AppConfig.photoMaxEdge,
        keepExif: false, // strips where and when the photo was taken
      );
      if (out == null) return null;
      final File file = File(out.path);
      if (await file.length() <= AppConfig.photoTargetBytes || quality == 35) return file;
    }
    return null;
  }

  Future<void> _editPrice() async {
    final AppServices s = AppScope.of(context);
    final TextEditingController retail = TextEditingController(text: formatQty(_product.retailPrice));
    final TextEditingController wholesale = TextEditingController(text: formatQty(_product.wholesalePrice));
    final bool? ok = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Prices'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            TextField(
              controller: retail,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Retail price'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: wholesale,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Wholesale price'),
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Save')),
        ],
      ),
    );
    final double? r = double.tryParse(retail.text);
    final double? w = double.tryParse(wholesale.text);
    retail.dispose();
    wholesale.dispose();
    if (ok != true || r == null || w == null || !mounted) return;

    // The old values go too: the office refuses the change if someone else has
    // moved the price since this phone last looked.
    await s.catalog.updateProduct(
      id: _product.id,
      fields: <String, Object?>{'retail_price': r, 'wholesale_price': w},
      base: <String, Object?>{'retail_price': _product.retailPrice, 'wholesale_price': _product.wholesalePrice},
    );
    await s.sync.refreshCounts();
    unawaited(s.sync.syncNow());
    if (mounted) showMessage(context, 'Price change queued for the office.');
  }

  Future<void> _adjustStock() async {
    final AppServices s = AppScope.of(context);
    final TextEditingController amount = TextEditingController();
    final TextEditingController reason = TextEditingController();
    bool receiving = true;

    final bool? ok = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => StatefulBuilder(
        builder: (BuildContext context, StateSetter setLocal) => AlertDialog(
          title: const Text('Change stock'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              SegmentedButton<bool>(
                segments: const <ButtonSegment<bool>>[
                  ButtonSegment<bool>(value: true, label: Text('Received')),
                  ButtonSegment<bool>(value: false, label: Text('Removed')),
                ],
                selected: <bool>{receiving},
                onSelectionChanged: (Set<bool> v) => setLocal(() => receiving = v.first),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amount,
                autofocus: true,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'How many'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: reason,
                decoration: const InputDecoration(labelText: 'Reason', hintText: 'e.g. delivery, damaged'),
              ),
            ],
          ),
          actions: <Widget>[
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Save')),
          ],
        ),
      ),
    );
    final double? qty = double.tryParse(amount.text);
    final String why = reason.text.trim();
    amount.dispose();
    reason.dispose();
    if (ok != true || qty == null || qty <= 0 || !mounted) return;

    // Sent as a change, never as a new total, so two phones adjusting the same
    // product both count instead of one overwriting the other.
    await s.catalog.adjustStock(
      productId: _product.id,
      locationId: widget.locationId,
      delta: receiving ? qty : -qty,
      reason: why.isEmpty ? (receiving ? 'Received' : 'Removed') : why,
      receiving: receiving,
    );
    await s.sync.refreshCounts();
    unawaited(s.sync.syncNow());
    if (mounted) showMessage(context, 'Stock change queued for the office.');
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;
    final MoneyFormat money = MoneyFormat(symbol: session.currencySymbol, decimals: session.decimals);
    final bool canEdit = session.can('products.update');

    return Scaffold(
      appBar: AppBar(title: Text(_product.name, maxLines: 1, overflow: TextOverflow.ellipsis)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Center(child: ProductImage(cache: s.images, image: _product.image, size: 180, radius: 20)),
          const SizedBox(height: 12),
          if (canEdit)
            Center(
              child: OutlinedButton.icon(
                onPressed: _busy ? null : _takePhoto,
                icon: _busy
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.photo_camera_outlined),
                label: Text(_product.image == null ? 'Add a photo' : 'Replace the photo'),
              ),
            ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: <Widget>[
                ListTile(
                  title: const Text('Retail price'),
                  trailing: Text(money.formatDouble(_product.retailPrice),
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  onTap: canEdit ? _editPrice : null,
                ),
                const Divider(height: 1),
                ListTile(
                  title: const Text('Wholesale price'),
                  trailing: Text(money.formatDouble(_product.wholesalePrice),
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  onTap: canEdit ? _editPrice : null,
                ),
                const Divider(height: 1),
                ListTile(
                  title: const Text('In stock here'),
                  subtitle: Text(_product.qty <= 0 ? 'Nothing on the shelf' : 'At this location'),
                  trailing: Text(formatQty(_product.qty),
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                        color: _product.qty <= 0 ? Theme.of(context).colorScheme.error : null,
                      )),
                  onTap: canEdit ? _adjustStock : null,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: <Widget>[
                if (_product.sku != null && _product.sku!.isNotEmpty)
                  ListTile(dense: true, title: const Text('SKU'), trailing: Text(_product.sku!)),
                if (_product.barcode != null && _product.barcode!.isNotEmpty)
                  ListTile(dense: true, title: const Text('Barcode'), trailing: Text(_product.barcode!)),
                ListTile(
                  dense: true,
                  title: const Text('Sold by'),
                  trailing: Text(_product.allowsFractions ? 'Measure (decimals allowed)' : 'Whole pieces'),
                ),
              ],
            ),
          ),
          if (canEdit) ...<Widget>[
            const SizedBox(height: 12),
            FilledButton.tonalIcon(
              onPressed: _adjustStock,
              icon: const Icon(Icons.inventory_outlined),
              label: const Text('Record stock received or removed'),
            ),
          ],
        ],
      ),
    );
  }
}
