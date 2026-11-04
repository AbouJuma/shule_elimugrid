import '../../../core/network/api_client.dart';
import '../domain/trip_models.dart';

class TripRepository {
  TripRepository(this._client);

  final ApiClient _client;

  /// The signed-in driver's running trip, or null.
  Future<Trip?> current() async {
    final body = await _client.get('/transport/trips/current');
    final data = body['data'];

    return data == null ? null : Trip.fromJson(data as Map<String, dynamic>);
  }

  Future<List<Trip>> active() async {
    final body = await _client.get('/transport/trips/active');

    return (body['data'] as List<dynamic>).map((t) => Trip.fromJson(t as Map<String, dynamic>)).toList();
  }

  Future<Trip> detail(String id) async {
    final body = await _client.get('/transport/trips/$id');

    return Trip.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Trip> start({required String vehicleId, required String routeId, required String direction}) async {
    final body = await _client.post('/transport/trips', data: {'vehicle_id': vehicleId, 'route_id': routeId, 'direction': direction});

    return Trip.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Sends one position. Returns the name of the stop the server just marked
  /// as reached (the bus came within range of it), if any.
  Future<String?> sendLocation(String tripId, {required double latitude, required double longitude, double? speedKmh}) async {
    final body = await _client.post(
      '/transport/trips/$tripId/location',
      data: {'latitude': latitude, 'longitude': longitude, 'speed_kmh': ?speedKmh},
    );

    return ((body['data'] as Map<String, dynamic>)['arrived_stop'] as Map<String, dynamic>?)?['name'] as String?;
  }

  Future<Trip> arrive(String tripId, int stopId) async {
    final body = await _client.post('/transport/trips/$tripId/stops/$stopId/arrive');

    return Trip.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Trip> end(String tripId) async {
    final body = await _client.post('/transport/trips/$tripId/end');

    return Trip.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<List<TransportRouteModel>> routes() async {
    final body = await _client.get('/transport-routes', query: {'per_page': 100});

    return (body['data'] as List<dynamic>).map((r) => TransportRouteModel.fromJson(r as Map<String, dynamic>)).toList();
  }
}
