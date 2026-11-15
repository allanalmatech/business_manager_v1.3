import 'dart:async';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/config.dart';
import '../../core/result.dart';
import '../../data/repositories/sync_repository.dart';
import '../../domain/models/catalog.dart';
import '../../widgets/common.dart';
import 'printers_screen.dart';

/// Account, printer, sync and storage. Everything a cashier might need to
/// check when something looks wrong, in one short list.
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  String _printerName = 'Not chosen';
  int _cacheBytes = 0;
  int _products = 0;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    final AppServices s = AppScope.of(context);
    final String name = (await s.db.getMeta('printer_name')) ?? '';
    final int bytes = await s.images.sizeBytes();
    final int products = await s.catalog.dao.productCount();
    if (!mounted) return;
    setState(() {
      _printerName = name.isEmpty ? 'Not chosen' : name;
      _cacheBytes = bytes;
      _products = products;
    });
  }

  Future<void> _signOut() async {
    final AppServices s = AppScope.of(context);
    final Result<void> res = await s.auth.signOut();
    if (!mounted) return;
    if (res is Err<void>) {
      final bool force = await confirm(
        context,
        'Still waiting to send',
        '${res.message}\n\nSigning out now would lose that work.',
        yes: 'Sign out anyway',
        danger: true,
      );
      if (!force) return;
      await s.auth.signOut(force: true);
    }
    if (mounted) s.sync.markSignedOut(); // sends the app back to the sign-in screen
  }

  @override
  Widget build(BuildContext context) {
    final AppServices s = AppScope.of(context);
    final Session session = s.session;

    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListenableBuilder(
        listenable: s.sync,
        builder: (BuildContext context, _) {
          final SyncStatusInfo status = s.sync.status;
          return ListView(
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.all(16),
                child: Card(
                  child: ListTile(
                    leading: CircleAvatar(child: Text(session.username.isEmpty ? '?' : session.username[0].toUpperCase())),
                    title: Text(session.name.isEmpty ? session.username : session.name),
                    subtitle: Text('${session.role} · ${session.businessName}'),
                  ),
                ),
              ),
              const _Heading('Office'),
              ListTile(
                leading: const Icon(Icons.sync),
                title: const Text('Sync now'),
                subtitle: Text(_syncLine(status)),
                trailing: status.state == SyncState.syncing
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.chevron_right),
                onTap: () => s.sync.syncNow(force: true),
              ),
              ListTile(
                leading: const Icon(Icons.cloud_sync_outlined),
                title: const Text('Download everything again'),
                subtitle: const Text('Use if prices or stock look wrong on this phone'),
                onTap: () async {
                  if (!await confirm(context, 'Download everything again?',
                      'The phone will replace its copy of the products and customers with the office\'s. '
                      'Anything waiting to be sent is sent first.')) {
                    return;
                  }
                  final Result<void> res = await s.sync.resetAndResync();
                  if (!mounted) return;
                  showMessage(context, res is Err<void> ? res.message : 'Up to date.', error: res is Err<void>);
                  await _load();
                },
              ),
              const _Heading('Printing'),
              ListTile(
                leading: const Icon(Icons.print_outlined),
                title: const Text('Receipt printer'),
                subtitle: Text(_printerName),
                trailing: const Icon(Icons.chevron_right),
                onTap: () async {
                  await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const PrintersScreen()));
                  await _load();
                },
              ),
              const _Heading('This phone'),
              ListTile(
                leading: const Icon(Icons.inventory_2_outlined),
                title: const Text('Products stored'),
                trailing: Text('$_products'),
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Product photos kept'),
                subtitle: Text('${_mb(_cacheBytes)} of ${_mb(AppConfig.imageCacheBudgetBytes)} — '
                    'the least used are removed automatically'),
                trailing: TextButton(
                  onPressed: () async {
                    await s.images.clear();
                    await _load();
                    if (mounted) showMessage(context, 'Photos cleared. They download again as needed.');
                  },
                  child: const Text('Clear'),
                ),
              ),
              ListTile(
                leading: const Icon(Icons.dns_outlined),
                title: const Text('Shop server'),
                subtitle: Text(AppConfig.baseUrl),
              ),
              const Divider(),
              ListTile(
                leading: Icon(Icons.logout, color: Theme.of(context).colorScheme.error),
                title: Text('Sign out', style: TextStyle(color: Theme.of(context).colorScheme.error)),
                subtitle: status.pending > 0
                    ? Text('${status.pending} item(s) still to send')
                    : const Text('Sales already sent stay at the office'),
                onTap: _signOut,
              ),
              const SizedBox(height: 24),
            ],
          );
        },
      ),
    );
  }

  static String _syncLine(SyncStatusInfo s) {
    if (s.state == SyncState.syncing) return 'Sending…';
    if (s.pending > 0) return '${s.pending} item(s) waiting';
    if (s.lastSyncAt != null) {
      final Duration ago = DateTime.now().difference(s.lastSyncAt!);
      if (ago.inMinutes < 1) return 'Up to date, just now';
      if (ago.inHours < 1) return 'Up to date, ${ago.inMinutes} minute(s) ago';
      return 'Last synced ${ago.inHours} hour(s) ago';
    }
    return s.message ?? 'Not synced yet';
  }

  static String _mb(int bytes) => '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
}

class _Heading extends StatelessWidget {
  const _Heading(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
        child: Text(text.toUpperCase(),
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 1,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            )),
      );
}
