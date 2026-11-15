import 'dart:io';

import 'package:flutter/material.dart';

import '../data/local/image_cache.dart';

/// Shows a product photo from the phone's cache, downloading it the first time.
/// Falls back to an icon, so a product without a picture, or a phone with no
/// connection, still looks deliberate.
class ProductImage extends StatefulWidget {
  const ProductImage({super.key, required this.cache, required this.image, this.size = 56, this.radius = 12});

  final ProductImageCache cache;
  final String? image;
  final double size;
  final double radius;

  @override
  State<ProductImage> createState() => _ProductImageState();
}

class _ProductImageState extends State<ProductImage> {
  Future<File?>? _future;

  @override
  void initState() {
    super.initState();
    _future = widget.cache.fileFor(widget.image);
  }

  @override
  void didUpdateWidget(ProductImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.image != widget.image) {
      _future = widget.cache.fileFor(widget.image);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme scheme = Theme.of(context).colorScheme;
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.radius),
      child: SizedBox(
        width: widget.size,
        height: widget.size,
        child: ColoredBox(
          color: scheme.surfaceContainerHighest,
          child: FutureBuilder<File?>(
            future: _future,
            builder: (BuildContext context, AsyncSnapshot<File?> snap) {
              final File? file = snap.data;
              if (file == null) {
                return Icon(Icons.inventory_2_outlined, size: widget.size * .45, color: scheme.outline);
              }
              return Image.file(
                file,
                fit: BoxFit.cover,
                gaplessPlayback: true,
                errorBuilder: (_, __, ___) =>
                    Icon(Icons.inventory_2_outlined, size: widget.size * .45, color: scheme.outline),
              );
            },
          ),
        ),
      ),
    );
  }
}
