/// Build-time settings. Point the app at a shop's own server with:
///   flutter build apk --dart-define=BM_BASE_URL=https://shop.example.com
class AppConfig {
  static const String baseUrl =
      String.fromEnvironment('BM_BASE_URL', defaultValue: 'https://demo.example.com');

  /// How much of the image cache we keep on the phone.
  static const int imageCacheBudgetBytes = 60 * 1024 * 1024;

  /// Photos are shrunk to this before upload, so a sale over a weak connection
  /// costs kilobytes rather than megabytes.
  static const int photoMaxEdge = 1280;
  static const int photoTargetBytes = 200 * 1024;

  /// Catalogue rows per pull request.
  static const int syncPageSize = 500;

  /// Try again after this long when a sync fails, doubling up to the maximum.
  static const Duration syncRetryBase = Duration(seconds: 5);
  static const Duration syncRetryMax = Duration(minutes: 5);
  static const Duration syncInterval = Duration(minutes: 3);

  static const Duration requestTimeout = Duration(seconds: 25);
}
