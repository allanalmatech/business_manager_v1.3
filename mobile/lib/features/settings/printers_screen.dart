import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/result.dart';
import '../../platform/bluetooth_printer.dart';
import '../../platform/escpos.dart';
import '../../widgets/common.dart';

/// Choosing the receipt printer. Pairing happens in the phone's own Bluetooth
/// settings; this picks which paired printer to use and proves it works.
class PrintersScreen extends StatefulWidget {
  const PrintersScreen({super.key});

  @override
  State<PrintersScreen> createState() => _PrintersScreenState();
}

class _PrintersScreenState extends State<PrintersScreen> {
  List<BtDevice> _devices = <BtDevice>[];
  String? _selected;
  String? _error;
  bool _loading = true;
  bool _printing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final AppServices s = AppScope.of(context);
    setState(() => _loading = true);
    final String? saved = await s.db.getMeta('printer_address');
    final Result<List<BtDevice>> res = await s.printer.pairedDevices();
    if (!mounted) return;
    setState(() {
      _loading = false;
      _selected = saved;
      _devices = res.valueOrNull ?? <BtDevice>[];
      _error = res.errorOrNull;
    });
  }

  Future<void> _choose(BtDevice d) async {
    final AppServices s = AppScope.of(context);
    await s.db.setMeta('printer_address', d.address);
    await s.db.setMeta('printer_name', d.name);
    if (mounted) setState(() => _selected = d.address);
  }

  Future<void> _test(BtDevice d) async {
    final AppServices s = AppScope.of(context);
    setState(() => _printing = true);
    final EscPos e = EscPos(columns: s.session.receiptWidth >= 80 ? 48 : 32);
    e.reset().align('center').bold(true).line(s.session.businessName).bold(false);
    e.line('Printer test').rule();
    e.align('left').row('Printer', d.name).row('Paper', '${s.session.receiptWidth} mm');
    e.rule().align('center').line('If you can read this, it works.').feed(1).cut();

    final Result<void> res = await s.printer.print(d.address, e.bytes());
    if (!mounted) return;
    setState(() => _printing = false);
    showMessage(context, res is Err<void> ? res.message : 'Test sent to ${d.name}.',
        error: res is Err<void>);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Receipt printer'),
        actions: <Widget>[
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh), tooltip: 'Look again'),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _devices.isEmpty
              ? EmptyState(
                  icon: Icons.print_disabled_outlined,
                  title: 'No printer paired',
                  message: _error ??
                      'Pair the printer in the phone\'s Bluetooth settings first, then come back and it will appear here.',
                  action: OutlinedButton.icon(
                    onPressed: _load,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Look again'),
                  ),
                )
              : ListView(
                  children: <Widget>[
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                      ),
                    ..._devices.map((BtDevice d) => RadioListTile<String>(
                          value: d.address,
                          groupValue: _selected,
                          onChanged: (_) => _choose(d),
                          title: Text(d.name),
                          subtitle: Text(d.address),
                          secondary: IconButton(
                            icon: _printing
                                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                : const Icon(Icons.print_outlined),
                            tooltip: 'Print a test',
                            onPressed: _printing ? null : () => _test(d),
                          ),
                        )),
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'Receipts are sent straight to the printer over Bluetooth, so printing works with no network at all.',
                        style: TextStyle(fontSize: 12),
                      ),
                    ),
                  ],
                ),
    );
  }
}
