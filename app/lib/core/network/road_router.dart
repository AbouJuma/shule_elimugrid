import 'package:dio/dio.dart';
import 'package:latlong2/latlong.dart';
import '../config/app_config.dart';

/// Turns an ordered list of stops into a line that follows real roads, using an
/// OSRM-compatible service ([AppConfig.routingUrl]).
///
/// Best-effort by design: a route line is decoration, so any failure (offline,
/// service down, no road between two points, service turned off) returns null and
/// the map keeps its straight dashed line between stops. Results are cached for
/// the life of the app, per exact list of points, so a live map that redraws
/// every few seconds asks the service once.
class RoadRouter {
  RoadRouter({Dio? dio, String? baseUrl})
    : _dio = dio ?? Dio(BaseOptions(connectTimeout: const Duration(seconds: 8), receiveTimeout: const Duration(seconds: 15))),
      _baseUrl = baseUrl ?? AppConfig.routingUrl;

  static RoadRouter instance = RoadRouter();

  /// OSRM's demo server refuses very long requests; a school route has far fewer stops than this.
  static const maxWaypoints = 25;

  final Dio _dio;
  final String _baseUrl;
  final Map<String, List<LatLng>?> _cache = {};
  final Map<String, Future<List<LatLng>?>> _inFlight = {};

  bool get enabled => _baseUrl.isNotEmpty;

  static String keyFor(List<LatLng> points) => points.map((p) => '${p.latitude.toStringAsFixed(5)},${p.longitude.toStringAsFixed(5)}').join(';');

  /// The cached line for exactly these points, if it has been fetched already.
  List<LatLng>? cached(List<LatLng> points) => _cache[keyFor(points)];

  Future<List<LatLng>?> route(List<LatLng> points) {
    if (!enabled || points.length < 2 || points.length > maxWaypoints) return Future.value(null);

    final key = keyFor(points);
    if (_cache.containsKey(key)) return Future.value(_cache[key]);

    return _inFlight[key] ??= _fetch(points).then((line) {
      // Remember successes; a failure is retried on the next request (a bus
      // on a patchy connection shouldn't lose its road line for the whole session).
      if (line != null) _cache[key] = line;
      _inFlight.remove(key);

      return line;
    });
  }

  Future<List<LatLng>?> _fetch(List<LatLng> points) async {
    try {
      final coords = points.map((p) => '${p.longitude},${p.latitude}').join(';');
      final response = await _dio.get<Map<String, dynamic>>(
        '${_baseUrl.replaceAll(RegExp(r'/+$'), '')}/route/v1/driving/$coords',
        queryParameters: {'overview': 'full', 'geometries': 'geojson', 'steps': 'false'},
      );

      final body = response.data;
      if (body == null || body['code'] != 'Ok') return null;

      final routes = body['routes'] as List<dynamic>;
      if (routes.isEmpty) return null;

      final line = (routes.first['geometry']['coordinates'] as List<dynamic>).map((c) => LatLng((c[1] as num).toDouble(), (c[0] as num).toDouble())).toList();

      return line.length >= 2 ? line : null;
    } catch (_) {
      return null;
    }
  }
}
