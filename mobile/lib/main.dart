import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations(<DeviceOrientation>[
    DeviceOrientation.portraitUp,
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
  ]);

  final AppServices services = AppServices.build();
  // Opening straight into the till matters more than a fresh sync: the saved
  // session is enough to keep selling with no network.
  final bool signedIn = (await services.auth.restore()) != null;
  runApp(BusinessManagerApp(services: services, startSignedIn: signedIn));
}
