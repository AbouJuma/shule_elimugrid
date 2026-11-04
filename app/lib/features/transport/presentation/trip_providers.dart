import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/trip_repository.dart';
import '../domain/trip_models.dart';

final tripRepositoryProvider = Provider<TripRepository>((ref) => TripRepository(ref.watch(apiClientProvider)));

/// The signed-in driver's running trip (null when none). Refreshed by
/// invalidating it after start/end/arrive.
final driverTripProvider = FutureProvider.autoDispose<Trip?>((ref) => ref.watch(tripRepositoryProvider).current());

/// A trip re-fetched every few seconds — the live view. Stops polling when
/// nothing is watching it (autoDispose).
final tripDetailProvider = StreamProvider.autoDispose.family<Trip, String>((ref, id) async* {
  final repo = ref.watch(tripRepositoryProvider);
  var first = true;

  while (true) {
    try {
      yield await repo.detail(id);
      first = false;
    } catch (_) {
      // A network blip mid-trip shouldn't blank the map: keep the last value
      // and try again. Only the very first load reports its error.
      if (first) rethrow;
    }

    await Future<void>.delayed(const Duration(seconds: 5));
  }
});

/// Trips running right now, polled.
final activeTripsProvider = StreamProvider.autoDispose<List<Trip>>((ref) async* {
  final repo = ref.watch(tripRepositoryProvider);
  var first = true;

  while (true) {
    try {
      yield await repo.active();
      first = false;
    } catch (_) {
      if (first) rethrow;
    }

    await Future<void>.delayed(const Duration(seconds: 8));
  }
});

final routesProvider = FutureProvider.autoDispose<List<TransportRouteModel>>((ref) => ref.watch(tripRepositoryProvider).routes());
