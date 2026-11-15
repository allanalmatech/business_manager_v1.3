import 'dart:math';

/// A version-4 UUID. Each queued action carries one, which is what lets the
/// phone retry forever without the server recording the same sale twice.
String newUuid() {
  final Random r = Random.secure();
  final List<int> b = List<int>.generate(16, (_) => r.nextInt(256));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  String hex(int start, int end) =>
      b.sublist(start, end).map((int x) => x.toRadixString(16).padLeft(2, '0')).join();
  return '${hex(0, 4)}-${hex(4, 6)}-${hex(6, 8)}-${hex(8, 10)}-${hex(10, 16)}';
}
