/// Money is held in minor units (whole cents/shillings) so that adding a
/// receipt up never drifts the way doubles do.
class Money {
  const Money._(this.minor);

  factory Money.fromMinor(int minor) => Money._(minor);

  /// Reads a server amount (a JSON number or string) into minor units.
  factory Money.fromServer(Object? value, {int decimals = 0}) {
    final double d = value is num ? value.toDouble() : double.tryParse('${value ?? 0}') ?? 0;
    return Money._((d * _pow10(decimals)).round());
  }

  final int minor;

  static int _pow10(int n) {
    int r = 1;
    for (int i = 0; i < n; i++) {
      r *= 10;
    }
    return r;
  }

  Money operator +(Money other) => Money._(minor + other.minor);
  Money operator -(Money other) => Money._(minor - other.minor);
  Money timesQty(double qty) => Money._((minor * qty).round());
  Money percent(double rate) => Money._((minor * rate / 100).round());

  bool get isZero => minor == 0;
  bool get isPositive => minor > 0;

  double toServer(int decimals) => minor / _pow10(decimals);
}

/// Formats amounts the way the shop's settings ask for.
class MoneyFormat {
  const MoneyFormat({this.symbol = '', this.decimals = 0});

  final String symbol;
  final int decimals;

  String format(Money m, {bool withSymbol = true}) {
    final int unit = Money._pow10(decimals);
    final bool negative = m.minor < 0;
    final int abs = m.minor.abs();
    final String whole = grouped(abs ~/ unit);
    final String frac = decimals == 0 ? '' : '.${(abs % unit).toString().padLeft(decimals, '0')}';
    return '${negative ? '-' : ''}${withSymbol ? symbol : ''}$whole$frac';
  }

  String formatDouble(double v, {bool withSymbol = true}) =>
      format(Money.fromServer(v, decimals: decimals), withSymbol: withSymbol);

  /// 1500 -> "1,500". Used for the quick-amount buttons at the till.
  static String grouped(int v) {
    final String s = v.toString();
    final StringBuffer out = StringBuffer();
    for (int i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) out.write(',');
      out.write(s[i]);
    }
    return out.toString();
  }
}

/// Quantities print without trailing zeros: 2, not 2.000.
String formatQty(double qty) {
  if (qty == qty.roundToDouble()) return qty.round().toString();
  return qty.toStringAsFixed(3).replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
}
