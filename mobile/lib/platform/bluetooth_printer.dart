import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/services.dart';

import '../core/result.dart';

class BtDevice {
  const BtDevice({required this.name, required this.address, this.bonded = true});

  factory BtDevice.fromMap(Map<Object?, Object?> m) => BtDevice(
        name: (m['name'] as String?) ?? 'Printer',
        address: (m['address'] as String?) ?? '',
        bonded: m['bonded'] != false,
      );

  final String name;
  final String address;
  final bool bonded;
}

/// Talks to the small piece of Android code in this project that opens a plain
/// Bluetooth serial connection to a receipt printer and writes bytes to it.
///
/// Deliberately a raw socket rather than a printing library: the cheap printers
/// in common use here speak ESC/POS over serial and nothing else, and this way
/// the app sends exactly the bytes it built.
class BluetoothPrinter {
  BluetoothPrinter({MethodChannel? channel})
      : _channel = channel ?? const MethodChannel('ug.almatech.businessmanager/printer');

  final MethodChannel _channel;

  /// Printers already paired in Android's Bluetooth settings.
  Future<Result<List<BtDevice>>> pairedDevices() async {
    try {
      final List<Object?>? list = await _channel.invokeMethod<List<Object?>>('paired');
      final List<BtDevice> out = <BtDevice>[];
      for (final Object? m in list ?? <Object?>[]) {
        if (m is Map<Object?, Object?>) out.add(BtDevice.fromMap(m));
      }
      return Ok<List<BtDevice>>(out);
    } on PlatformException catch (e) {
      return Err<List<BtDevice>>(_explain(e));
    } on MissingPluginException {
      return const Err<List<BtDevice>>('Bluetooth printing is only available in the Android app.');
    }
  }

  /// Sends the bytes. The connection is opened and closed per receipt, which is
  /// slower than holding it open but survives the printer being switched off
  /// between customers, which is how they are actually used.
  Future<Result<void>> print(String address, Uint8List bytes) async {
    try {
      await _channel.invokeMethod<void>('print', <String, Object?>{
        'address': address,
        'bytes': bytes,
      });
      return const Ok<void>(null);
    } on PlatformException catch (e) {
      return Err<void>(_explain(e));
    } on MissingPluginException {
      return const Err<void>('Bluetooth printing is only available in the Android app.');
    }
  }

  Future<Result<void>> openDrawer(String address, Uint8List bytes) => print(address, bytes);

  String _explain(PlatformException e) {
    switch (e.code) {
      case 'no_bluetooth':
        return 'This phone has no Bluetooth.';
      case 'bluetooth_off':
        return 'Bluetooth is switched off. Turn it on and try again.';
      case 'no_permission':
        return 'The app needs permission to use Bluetooth. Allow it in the phone settings.';
      case 'not_paired':
        return 'That printer is not paired. Pair it in the phone\'s Bluetooth settings first.';
      case 'connect_failed':
        return 'Could not reach the printer. Check it is switched on and close by.';
      case 'write_failed':
        return 'The connection to the printer dropped while printing.';
      default:
        return e.message ?? 'Printing failed.';
    }
  }
}
