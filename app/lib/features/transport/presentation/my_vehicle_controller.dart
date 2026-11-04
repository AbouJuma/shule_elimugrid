import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/vehicle_model.dart';
import 'vehicles_controller.dart';

/// A driver's own assigned vehicle. There's no dedicated "my vehicle" API
/// endpoint — Drivers already hold `transport.view`, so this reuses the
/// plain vehicle list and matches by driver id client-side. Fine at
/// school scale (tens of vehicles); would need a real endpoint if that
/// ever stopped being true.
final myVehicleProvider = FutureProvider.autoDispose<VehicleModel?>((ref) async {
  final user = ref.watch(authControllerProvider).value?.user;
  if (user == null) return null;

  final vehicles = await ref.watch(vehicleRepositoryProvider).list();
  for (final vehicle in vehicles.items) {
    if (vehicle.driver?.id == user.id) return vehicle;
  }
  return null;
});
