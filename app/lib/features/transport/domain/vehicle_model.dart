/// Mirrors App\Http\Resources\VehicleResource on the backend.
class VehicleLastLocation {
  const VehicleLastLocation({
    required this.latitude,
    required this.longitude,
    required this.recordedAt,
  });

  final double latitude;
  final double longitude;
  final String recordedAt;

  factory VehicleLastLocation.fromJson(Map<String, dynamic> json) => VehicleLastLocation(
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    recordedAt: json['recorded_at'] as String,
  );
}

class VehicleDriver {
  const VehicleDriver({required this.id, required this.name, this.phone});

  final String id;
  final String name;
  final String? phone;

  factory VehicleDriver.fromJson(Map<String, dynamic> json) => VehicleDriver(
    id: json['id'] as String,
    name: json['name'] as String,
    phone: json['phone'] as String?,
  );
}

class VehicleModel {
  const VehicleModel({
    required this.id,
    required this.registrationNumber,
    this.make,
    this.model,
    this.capacity,
    required this.status,
    this.driver,
    this.lastLocation,
  });

  final String id;
  final String registrationNumber;
  final String? make;
  final String? model;
  final int? capacity;
  final String status;
  final VehicleDriver? driver;
  final VehicleLastLocation? lastLocation;

  factory VehicleModel.fromJson(Map<String, dynamic> json) => VehicleModel(
    id: json['id'] as String,
    registrationNumber: json['registration_number'] as String,
    make: json['make'] as String?,
    model: json['model'] as String?,
    capacity: json['capacity'] as int?,
    status: json['status'] as String,
    driver: json['driver'] != null ? VehicleDriver.fromJson(json['driver'] as Map<String, dynamic>) : null,
    lastLocation: json['last_location'] != null
        ? VehicleLastLocation.fromJson(json['last_location'] as Map<String, dynamic>)
        : null,
  );
}
