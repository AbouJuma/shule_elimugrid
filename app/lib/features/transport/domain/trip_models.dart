import 'package:latlong2/latlong.dart';

/// Mirrors App\Http\Resources\TransportTripResource (detailed form).
class TripStop {
  const TripStop({
    required this.id,
    required this.name,
    required this.sequence,
    this.latitude,
    this.longitude,
    this.pickupTime,
    this.dropoffTime,
    this.students = 0,
    this.arrivedAt,
    this.isNext = false,
  });

  final int id;
  final String name;
  final int sequence;
  final double? latitude;
  final double? longitude;
  final String? pickupTime;
  final String? dropoffTime;
  final int students;
  final DateTime? arrivedAt;
  final bool isNext;

  bool get arrived => arrivedAt != null;
  bool get hasPoint => latitude != null && longitude != null;
  LatLng? get point => hasPoint ? LatLng(latitude!, longitude!) : null;

  factory TripStop.fromJson(Map<String, dynamic> json) => TripStop(
    id: json['id'] as int,
    name: json['name'] as String,
    sequence: json['sequence'] as int,
    latitude: (json['latitude'] as num?)?.toDouble(),
    longitude: (json['longitude'] as num?)?.toDouble(),
    pickupTime: json['pickup_time'] as String?,
    dropoffTime: json['dropoff_time'] as String?,
    students: (json['students'] as num?)?.toInt() ?? 0,
    arrivedAt: json['arrived_at'] != null ? DateTime.parse(json['arrived_at'] as String).toLocal() : null,
    isNext: json['is_next'] as bool? ?? false,
  );
}

class TripPosition {
  const TripPosition({required this.latitude, required this.longitude, this.speedKmh, required this.recordedAt});

  final double latitude;
  final double longitude;
  final double? speedKmh;
  final DateTime recordedAt;

  LatLng get point => LatLng(latitude, longitude);

  factory TripPosition.fromJson(Map<String, dynamic> json) => TripPosition(
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    speedKmh: (json['speed_kmh'] as num?)?.toDouble(),
    recordedAt: DateTime.parse(json['recorded_at'] as String).toLocal(),
  );
}

/// Rough time to the next stop (straight-line distance × 1.3 at the bus's recent speed).
class NextStopEta {
  const NextStopEta({required this.stopId, required this.name, required this.distanceM, required this.etaMinutes});

  final int stopId;
  final String name;
  final int distanceM;
  final int etaMinutes;

  String get distanceLabel => distanceM >= 1000 ? '${(distanceM / 1000).toStringAsFixed(1)} km' : '$distanceM m';

  factory NextStopEta.fromJson(Map<String, dynamic> json) => NextStopEta(
    stopId: json['stop_id'] as int,
    name: json['name'] as String,
    distanceM: (json['distance_m'] as num).toInt(),
    etaMinutes: (json['eta_minutes'] as num).toInt(),
  );
}

class Trip {
  const Trip({
    required this.id,
    required this.status,
    required this.direction,
    required this.startedAt,
    this.endedAt,
    required this.vehicleId,
    required this.registrationNumber,
    this.driverName,
    required this.routeId,
    required this.routeName,
    this.stops = const [],
    this.visited = 0,
    this.total = 0,
    this.position,
    this.trail = const [],
    this.next,
  });

  final String id;
  final String status;
  final String direction;
  final DateTime startedAt;
  final DateTime? endedAt;
  final String vehicleId;
  final String registrationNumber;
  final String? driverName;
  final String routeId;
  final String routeName;
  final List<TripStop> stops;
  final int visited;
  final int total;
  final TripPosition? position;
  final List<LatLng> trail;
  final NextStopEta? next;

  bool get isActive => status == 'in_progress';
  bool get isMorning => direction == 'morning';
  TripStop? get nextStop => stops.where((s) => s.isNext).firstOrNull;
  double get progress => total == 0 ? 0 : visited / total;

  factory Trip.fromJson(Map<String, dynamic> json) {
    final vehicle = json['vehicle'] as Map<String, dynamic>;
    final route = json['route'] as Map<String, dynamic>;
    final progress = json['progress'] as Map<String, dynamic>?;

    return Trip(
      id: json['id'] as String,
      status: json['status'] as String,
      direction: json['direction'] as String,
      startedAt: DateTime.parse(json['started_at'] as String).toLocal(),
      endedAt: json['ended_at'] != null ? DateTime.parse(json['ended_at'] as String).toLocal() : null,
      vehicleId: vehicle['id'] as String? ?? '',
      registrationNumber: vehicle['registration_number'] as String,
      driverName: (vehicle['driver'] as Map<String, dynamic>?)?['name'] as String?,
      routeId: route['id'] as String,
      routeName: route['name'] as String,
      stops: (json['stops'] as List<dynamic>? ?? const []).map((s) => TripStop.fromJson(s as Map<String, dynamic>)).toList(),
      visited: (progress?['visited'] as num?)?.toInt() ?? 0,
      total: (progress?['total'] as num?)?.toInt() ?? 0,
      position: json['position'] != null ? TripPosition.fromJson(json['position'] as Map<String, dynamic>) : null,
      next: json['next'] != null ? NextStopEta.fromJson(json['next'] as Map<String, dynamic>) : null,
      trail: (json['trail'] as List<dynamic>? ?? const [])
          .map((p) => LatLng((p[0] as num).toDouble(), (p[1] as num).toDouble()))
          .toList(),
    );
  }
}

/// A bus route the driver can choose when starting a trip.
class TransportRouteModel {
  const TransportRouteModel({required this.id, required this.name, required this.status, this.vehicleId, this.stops = const []});

  final String id;
  final String name;
  final String status;
  final String? vehicleId;
  final List<RouteStopModel> stops;

  factory TransportRouteModel.fromJson(Map<String, dynamic> json) => TransportRouteModel(
    id: json['id'] as String,
    name: json['name'] as String,
    status: json['status'] as String? ?? 'active',
    vehicleId: (json['vehicle'] as Map<String, dynamic>?)?['id'] as String?,
    stops: (json['stops'] as List<dynamic>? ?? const []).map((s) => RouteStopModel.fromJson(s as Map<String, dynamic>)).toList(),
  );
}

class RouteStopModel {
  const RouteStopModel({required this.id, required this.name, required this.sequence, this.latitude, this.longitude, this.pickupTime, this.dropoffTime});

  final int id;
  final String name;
  final int sequence;
  final double? latitude;
  final double? longitude;
  final String? pickupTime;
  final String? dropoffTime;

  LatLng? get point => latitude != null && longitude != null ? LatLng(latitude!, longitude!) : null;

  factory RouteStopModel.fromJson(Map<String, dynamic> json) => RouteStopModel(
    id: json['id'] as int,
    name: json['name'] as String,
    sequence: json['sequence'] as int,
    latitude: (json['latitude'] as num?)?.toDouble(),
    longitude: (json['longitude'] as num?)?.toDouble(),
    pickupTime: json['pickup_time'] as String?,
    dropoffTime: json['dropoff_time'] as String?,
  );
}
