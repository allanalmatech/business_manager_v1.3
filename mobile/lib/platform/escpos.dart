import 'dart:convert';
import 'dart:typed_data';

/// Builds the bytes a thermal printer understands (ESC/POS).
///
/// Written out by hand rather than taken from a package: it is a few dozen
/// control codes, and the cheap Bluetooth printers sold locally vary enough
/// that being able to adjust them matters more than the convenience.
class EscPos {
  EscPos({this.columns = 32});

  /// 32 for 58 mm paper, 48 for 80 mm, at the normal font size.
  final int columns;

  final BytesBuilder _b = BytesBuilder();

  static const List<int> _esc = <int>[0x1b];
  static const List<int> _gs = <int>[0x1d];

  EscPos reset() => _raw(<int>[..._esc, 0x40]);

  EscPos align(String how) =>
      _raw(<int>[..._esc, 0x61, how == 'center' ? 1 : (how == 'right' ? 2 : 0)]);

  EscPos bold(bool on) => _raw(<int>[..._esc, 0x45, on ? 1 : 0]);

  /// 0 normal, 1 double height, 2 double width, 3 both.
  EscPos size(int mode) {
    final int n = switch (mode) { 1 => 0x01, 2 => 0x10, 3 => 0x11, _ => 0x00 };
    return _raw(<int>[..._gs, 0x21, n]);
  }

  EscPos text(String s) {
    // Latin-1 keeps the printer's default code page happy; anything outside it
    // is dropped rather than printed as noise.
    _b.add(latin1.encode(s.replaceAll(RegExp(r'[^\x20-\xFF\n]'), '')));
    return this;
  }

  EscPos line([String s = '']) => text('$s\n');

  EscPos feed([int lines = 1]) => _raw(<int>[..._esc, 0x64, lines.clamp(0, 255)]);

  EscPos rule([String ch = '-']) => line(ch * columns);

  /// A label on the left and a figure on the right, which is most of a receipt.
  EscPos row(String left, String right, {bool boldRow = false}) {
    final int space = columns - right.length;
    String l = left;
    if (l.length > space - 1 && space > 1) l = l.substring(0, space - 1);
    if (boldRow) bold(true);
    line(l.padRight(space) + right);
    if (boldRow) bold(false);
    return this;
  }

  /// A product line: name on its own row when long, then quantity and amount.
  EscPos item(String name, String qty, String amount) {
    if (name.length > columns) {
      line(name.substring(0, columns));
      return row('  $qty', amount);
    }
    line(name);
    return row('  $qty', amount);
  }

  EscPos qr(String data) {
    final List<int> d = latin1.encode(data);
    final int len = d.length + 3;
    return _raw(<int>[
      ..._gs, 0x28, 0x6b, 4, 0, 0x31, 0x41, 0x32, 0x00, // model 2
      ..._gs, 0x28, 0x6b, 3, 0, 0x31, 0x43, 6,          // dot size
      ..._gs, 0x28, 0x6b, 3, 0, 0x31, 0x45, 0x31,       // error correction
      ..._gs, 0x28, 0x6b, len & 0xff, (len >> 8) & 0xff, 0x31, 0x50, 0x30, ...d,
      ..._gs, 0x28, 0x6b, 3, 0, 0x31, 0x51, 0x30,       // print
    ]);
  }

  /// Opens the cash drawer wired to the printer.
  EscPos openDrawer() => _raw(<int>[..._esc, 0x70, 0x00, 0x19, 0xfa]);

  EscPos cut() {
    feed(3);
    return _raw(<int>[..._gs, 0x56, 0x42, 0x00]);
  }

  EscPos _raw(List<int> bytes) {
    _b.add(Uint8List.fromList(bytes));
    return this;
  }

  Uint8List bytes() => _b.toBytes();
}
