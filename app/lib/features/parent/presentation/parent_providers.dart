import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../../timetable/domain/timetable_models.dart';
import '../data/parent_repository.dart';
import '../domain/parent_models.dart';

final parentRepositoryProvider = Provider<ParentRepository>((ref) => ParentRepository(ref.watch(apiClientProvider)));

final myChildrenProvider = FutureProvider.autoDispose<List<ChildSummary>>((ref) => ref.watch(parentRepositoryProvider).children());

/// The child's bus, re-fetched every 5 s while the bus is on a trip (so the map
/// moves) and every 20 s otherwise (so the screen notices when a trip starts).
final childBusProvider = StreamProvider.autoDispose.family<ChildBus, String>((ref, childId) async* {
  final repo = ref.watch(parentRepositoryProvider);
  var first = true;
  var running = false;

  while (true) {
    try {
      final bus = await repo.bus(childId);
      running = bus.running;
      yield bus;
      first = false;
    } catch (_) {
      // A network blip shouldn't blank the map; only the first load reports its error.
      if (first) rethrow;
    }

    await Future<void>.delayed(Duration(seconds: running ? 5 : 20));
  }
});

final childFeesProvider = FutureProvider.autoDispose.family<ChildFees, String>((ref, id) => ref.watch(parentRepositoryProvider).fees(id));

final childAttendanceProvider = FutureProvider.autoDispose.family<ChildAttendance, String>((ref, id) => ref.watch(parentRepositoryProvider).attendance(id));

/// (childId, termId) — termId null means "the current/latest published term".
final childResultsProvider = FutureProvider.autoDispose.family<ChildResults, (String, int?)>(
  (ref, key) => ref.watch(parentRepositoryProvider).results(key.$1, termId: key.$2),
);

final childTimetableProvider = FutureProvider.autoDispose.family<TimetableData, String>((ref, id) => ref.watch(parentRepositoryProvider).timetable(id));
