import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/vehicle_repository.dart';
import '../domain/vehicle_model.dart';

final vehicleRepositoryProvider = Provider<VehicleRepository>(
  (ref) => VehicleRepository(ref.watch(apiClientProvider)),
);

/// Vehicle counts per school are small (tens, not thousands) — a single
/// unfiltered fetch is enough for now; add search/status filtering here
/// (mirroring the Students list) if that stops being true.
final vehiclesListProvider = FutureProvider.autoDispose<List<VehicleModel>>((ref) async {
  final result = await ref.watch(vehicleRepositoryProvider).list();
  return result.items;
});

final vehicleDetailProvider = FutureProvider.autoDispose.family<VehicleModel, String>(
  (ref, id) => ref.watch(vehicleRepositoryProvider).show(id),
);
