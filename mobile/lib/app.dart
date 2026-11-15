import 'package:flutter/material.dart';

import 'core/config.dart';
import 'core/theme.dart';
import 'data/local/dao.dart';
import 'data/local/database.dart';
import 'data/local/image_cache.dart';
import 'data/remote/api_client.dart';
import 'data/repositories/auth_repository.dart';
import 'data/repositories/sales_repository.dart';
import 'data/repositories/sync_repository.dart';
import 'domain/models/catalog.dart';
import 'domain/services/cart_service.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_shell.dart';
import 'platform/bluetooth_printer.dart';

/// Everything the screens need, built once and handed down the tree.
/// A plain InheritedWidget rather than a state-management package: the app has
/// one user and a handful of services, and this keeps the build small.
class AppServices {
  AppServices({
    required this.db,
    required this.api,
    required this.auth,
    required this.sync,
    required this.sales,
    required this.catalog,
    required this.images,
    required this.printer,
    required this.cart,
  });

  final LocalDb db;
  final ApiClient api;
  final AuthRepository auth;
  final SyncRepository sync;
  final SalesRepository sales;
  final CatalogRepository catalog;
  final ProductImageCache images;
  final BluetoothPrinter printer;
  final CartService cart;

  Session get session => auth.session!;

  static AppServices build() {
    final LocalDb db = LocalDb.instance;
    final ApiClient api = ApiClient();
    final OutboxDao outbox = OutboxDao(db);
    final SalesDao salesDao = SalesDao(db);
    final CatalogDao catalogDao = CatalogDao(db);
    return AppServices(
      db: db,
      api: api,
      auth: AuthRepository(api, db),
      sync: SyncRepository(api: api, db: db, outbox: outbox, sales: salesDao),
      sales: SalesRepository(db: db, sales: salesDao, outbox: outbox),
      catalog: CatalogRepository(outbox: outbox, dao: catalogDao),
      images: ProductImageCache(db: db, baseUrl: AppConfig.baseUrl),
      printer: BluetoothPrinter(),
      cart: CartService(
        session: const Session(
          userId: 0, username: '', name: '', role: '', permissions: <String>{},
          businessName: '', businessAddress: '', businessPhone: '',
          currencySymbol: '', decimals: 0, taxEnabled: false, taxName: 'Tax',
          taxRate: 0, taxInclusive: false, receiptHeader: '', receiptFooter: '',
          receiptWidth: 80, deviceId: 0,
        ),
      ),
    );
  }
}

class AppScope extends InheritedWidget {
  const AppScope({super.key, required this.services, required super.child});

  final AppServices services;

  static AppServices of(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<AppScope>()!.services;

  @override
  bool updateShouldNotify(AppScope oldWidget) => services != oldWidget.services;
}

class BusinessManagerApp extends StatefulWidget {
  const BusinessManagerApp({super.key, required this.services, required this.startSignedIn});

  final AppServices services;
  final bool startSignedIn;

  @override
  State<BusinessManagerApp> createState() => _BusinessManagerAppState();
}

class _BusinessManagerAppState extends State<BusinessManagerApp> {
  late bool _signedIn = widget.startSignedIn;

  @override
  void initState() {
    super.initState();
    if (_signedIn) _afterSignIn();
    widget.services.sync.addListener(_watchForSignOut);
  }

  @override
  void dispose() {
    widget.services.sync.removeListener(_watchForSignOut);
    super.dispose();
  }

  /// The server can end a device's session (a phone lost, a cashier who left).
  void _watchForSignOut() {
    if (widget.services.sync.signedOutByServer && _signedIn) {
      setState(() => _signedIn = false);
    }
  }

  void _afterSignIn() {
    widget.services.cart.session = widget.services.auth.session!;
    widget.services.sync.start();
  }

  @override
  Widget build(BuildContext context) {
    return AppScope(
      services: widget.services,
      child: MaterialApp(
        title: 'Business Manager',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        home: _signedIn
            ? const HomeShell()
            : LoginScreen(onSignedIn: () {
                _afterSignIn();
                setState(() => _signedIn = true);
              }),
      ),
    );
  }
}
