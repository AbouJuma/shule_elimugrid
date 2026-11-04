import 'package:flutter/foundation.dart' show kReleaseMode;

/// Runtime configuration. Override at build/run time with, e.g.:
///   flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8123/api/v1
///
/// A **release** build (`flutter build apk --release`, `--release` app
/// bundle, etc.) needs no flag at all: it already defaults to the real,
/// hosted backend below, so a production build is correct by default
/// rather than depending on someone remembering to pass --dart-define
/// every single time. A debug/profile build (flutter run, the emulator)
/// still defaults to the local dev server instead — an Android emulator
/// needs 10.0.2.2 rather than 127.0.0.1 to reach the host machine; pass
/// that via --dart-define there. --dart-define always wins over either
/// default, for the rare case of a release build against a staging server.
class AppConfig {
  AppConfig._();

  /// The backend once it's actually hosted (see FILE_PLAN's Phase 37) —
  /// update this one line if the production domain ever changes; every
  /// release build picks it up automatically from then on.
  static const String _productionApiBaseUrl = 'https://elimugrid.ecofieldgroup.com/api/v1';
  static const String _devApiBaseUrl = 'http://127.0.0.1:8123/api/v1';

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: kReleaseMode ? _productionApiBaseUrl : _devApiBaseUrl,
  );

  /// Map tile source. The default is OpenStreetMap's public server, which is
  /// fine for a pilot but not for a busy fleet (see its tile usage policy) —
  /// point a paid or self-hosted provider at it with, e.g.
  ///   --dart-define=TILE_URL_TEMPLATE=https://tiles.example.com/{z}/{x}/{y}.png
  static const String tileUrlTemplate = String.fromEnvironment(
    'TILE_URL_TEMPLATE',
    defaultValue: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  );

  /// OSRM-compatible routing service used to draw the route along real roads.
  /// The default is the public OSRM demo server: fine to try the feature, but it
  /// is a shared demo with no uptime promise and is not meant for production
  /// traffic — self-host OSRM (or use a paid provider) and point this at it:
  ///   --dart-define=ROUTING_URL=https://osrm.example.com
  /// Set it to an empty string to turn road routing off (straight lines only).
  static const String routingUrl = String.fromEnvironment(
    'ROUTING_URL',
    defaultValue: 'https://router.project-osrm.org',
  );
}
