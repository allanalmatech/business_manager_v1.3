/// Plain data the screens read. Every field comes from the phone's own
/// database, filled by sync; nothing here talks to the network.

class Product {
  const Product({
    required this.id,
    required this.name,
    this.sku,
    this.barcode,
    this.categoryId,
    this.unitType = 'pieces',
    this.unitName,
    this.wholesalePrice = 0,
    this.retailPrice = 0,
    this.image,
    this.qty = 0,
    this.isActive = true,
  });

  factory Product.fromRow(Map<String, Object?> r) => Product(
        id: r['id'] as int,
        name: (r['name'] as String?) ?? '',
        sku: r['sku'] as String?,
        barcode: r['barcode'] as String?,
        categoryId: r['category_id'] as int?,
        unitType: (r['unit_type'] as String?) ?? 'pieces',
        unitName: r['unit_name'] as String?,
        wholesalePrice: (r['wholesale_price'] as num?)?.toDouble() ?? 0,
        retailPrice: (r['retail_price'] as num?)?.toDouble() ?? 0,
        image: r['image'] as String?,
        qty: (r['qty'] as num?)?.toDouble() ?? 0,
        isActive: ((r['is_active'] as int?) ?? 1) == 1,
      );

  final int id;
  final String name;
  final String? sku;
  final String? barcode;
  final int? categoryId;
  final String unitType;
  final String? unitName;
  final double wholesalePrice;
  final double retailPrice;
  final String? image;

  /// On hand at the chosen location, less anything this phone has sold offline.
  final double qty;
  final bool isActive;

  /// Measured goods (kg, litres) may be sold in fractions; pieces may not.
  bool get allowsFractions => unitType == 'units';

  double priceFor(bool wholesale) => wholesale ? wholesalePrice : retailPrice;
}

class Customer {
  const Customer({required this.id, required this.name, this.phone, this.email, this.address, this.creditLimit = 0});

  factory Customer.fromRow(Map<String, Object?> r) => Customer(
        id: r['id'] as int,
        name: (r['name'] as String?) ?? '',
        phone: r['phone'] as String?,
        email: r['email'] as String?,
        address: r['address'] as String?,
        creditLimit: (r['credit_limit'] as num?)?.toDouble() ?? 0,
      );

  final int id;
  final String name;
  final String? phone;
  final String? email;
  final String? address;
  final double creditLimit;
}

class ShopLocation {
  const ShopLocation({required this.id, required this.name});

  factory ShopLocation.fromRow(Map<String, Object?> r) =>
      ShopLocation(id: r['id'] as int, name: (r['name'] as String?) ?? '');

  final int id;
  final String name;
}

/// Everything the server told us about the shop and the person signed in.
class Session {
  const Session({
    required this.userId,
    required this.username,
    required this.name,
    required this.role,
    required this.permissions,
    required this.businessName,
    required this.businessAddress,
    required this.businessPhone,
    required this.currencySymbol,
    required this.decimals,
    required this.taxEnabled,
    required this.taxName,
    required this.taxRate,
    required this.taxInclusive,
    required this.receiptHeader,
    required this.receiptFooter,
    required this.receiptWidth,
    required this.deviceId,
  });

  factory Session.fromJson(Map<String, Object?> j, {int deviceId = 0}) {
    final Map<String, Object?> u = (j['user'] as Map<String, Object?>?) ?? <String, Object?>{};
    final Map<String, Object?> b = (j['business'] as Map<String, Object?>?) ?? <String, Object?>{};
    final Map<String, Object?> m = (j['money'] as Map<String, Object?>?) ?? <String, Object?>{};
    final Map<String, Object?> t = (j['tax'] as Map<String, Object?>?) ?? <String, Object?>{};
    final Map<String, Object?> r = (j['receipt'] as Map<String, Object?>?) ?? <String, Object?>{};
    double asDouble(Object? v) => v is num ? v.toDouble() : double.tryParse('${v ?? 0}') ?? 0;
    return Session(
      userId: (u['id'] as num?)?.toInt() ?? 0,
      username: (u['username'] as String?) ?? '',
      name: (u['name'] as String?) ?? '',
      role: (u['role'] as String?) ?? '',
      permissions: ((j['permissions'] as List<Object?>?) ?? <Object?>[]).map((Object? e) => '$e').toSet(),
      businessName: (b['name'] as String?) ?? 'Business Manager',
      businessAddress: (b['address'] as String?) ?? '',
      businessPhone: (b['phone'] as String?) ?? '',
      currencySymbol: (m['currency_symbol'] as String?) ?? '',
      decimals: (m['decimal_places'] as num?)?.toInt() ?? 0,
      taxEnabled: t['enabled'] == true,
      taxName: (t['name'] as String?) ?? 'Tax',
      taxRate: asDouble(t['rate']),
      taxInclusive: t['inclusive'] == true,
      receiptHeader: (r['header'] as String?) ?? '',
      receiptFooter: (r['footer'] as String?) ?? '',
      receiptWidth: (r['width'] as num?)?.toInt() ?? 80,
      deviceId: deviceId,
    );
  }

  final int userId;
  final String username;
  final String name;
  final String role;
  final Set<String> permissions;
  final String businessName;
  final String businessAddress;
  final String businessPhone;
  final String currencySymbol;
  final int decimals;
  final bool taxEnabled;
  final String taxName;
  final double taxRate;
  final bool taxInclusive;
  final String receiptHeader;
  final String receiptFooter;
  final int receiptWidth;
  final int deviceId;

  bool can(String permission) => permissions.contains(permission);

  Map<String, Object?> toJson() => <String, Object?>{
        'user': <String, Object?>{'id': userId, 'username': username, 'name': name, 'role': role},
        'permissions': permissions.toList(),
        'business': <String, Object?>{'name': businessName, 'address': businessAddress, 'phone': businessPhone},
        'money': <String, Object?>{'currency_symbol': currencySymbol, 'decimal_places': decimals},
        'tax': <String, Object?>{'enabled': taxEnabled, 'name': taxName, 'rate': taxRate, 'inclusive': taxInclusive},
        'receipt': <String, Object?>{'header': receiptHeader, 'footer': receiptFooter, 'width': receiptWidth},
      };
}
