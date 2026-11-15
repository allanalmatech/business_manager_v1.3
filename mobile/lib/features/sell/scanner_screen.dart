import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Reads a barcode with the camera and returns the digits.
/// Only the first read is used, so a code in view is not added five times.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key, this.title = 'Scan a barcode'});

  final String title;

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const <BarcodeFormat>[
      BarcodeFormat.ean13,
      BarcodeFormat.ean8,
      BarcodeFormat.upcA,
      BarcodeFormat.upcE,
      BarcodeFormat.code128,
      BarcodeFormat.code39,
      BarcodeFormat.itf,
      BarcodeFormat.qrCode,
    ],
  );
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;
    for (final Barcode b in capture.barcodes) {
      final String? value = b.rawValue;
      if (value == null || value.trim().isEmpty) continue;
      _handled = true;
      Navigator.of(context).pop(value.trim());
      return;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: <Widget>[
          IconButton(
            tooltip: 'Torch',
            onPressed: () => _controller.toggleTorch(),
            icon: const Icon(Icons.flashlight_on_outlined),
          ),
          IconButton(
            tooltip: 'Switch camera',
            onPressed: () => _controller.switchCamera(),
            icon: const Icon(Icons.cameraswitch_outlined),
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (BuildContext context, MobileScannerException error, _) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    const Icon(Icons.no_photography_outlined, size: 48),
                    const SizedBox(height: 12),
                    const Text('The camera is not available.', textAlign: TextAlign.center),
                    const SizedBox(height: 6),
                    Text(
                      'Allow camera access in the phone settings, or type the code instead.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
            ),
          ),
          // A window to aim through: people point the phone far more accurately
          // when there is a frame to fill.
          IgnorePointer(
            child: Center(
              child: Container(
                width: 260,
                height: 150,
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.white.withOpacity(.9), width: 3),
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 36,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(.6),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Text('Hold the barcode inside the frame',
                    style: TextStyle(color: Colors.white)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
