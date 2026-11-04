import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../../attendance/data/attendance_repository.dart';
import '../../attendance/domain/attendance_models.dart';
import '../data/timetable_repository.dart';
import '../domain/timetable_models.dart';

final timetableRepositoryProvider = Provider<TimetableRepository>(
  (ref) => TimetableRepository(ref.watch(apiClientProvider)),
);

final myTimetableProvider = FutureProvider.autoDispose<TimetableData>(
  (ref) => ref.watch(timetableRepositoryProvider).mine(),
);

final classTimetableProvider = FutureProvider.autoDispose.family<TimetableData, int>(
  (ref, classId) => ref.watch(timetableRepositoryProvider).forClass(classId),
);

/// Classes to pick from — the same list attendance uses, which teachers can
/// read even without the wider classes.view permission.
final timetableClassesProvider = FutureProvider.autoDispose<List<AttendanceClass>>(
  (ref) => AttendanceRepository(ref.watch(apiClientProvider)).classes(),
);
