import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../../attendance/domain/attendance_models.dart';
import '../data/staff_attendance_repository.dart';
import '../domain/staff_attendance_models.dart';

final staffAttendanceRepositoryProvider = Provider<StaffAttendanceRepository>(
  (ref) => StaffAttendanceRepository(ref.watch(apiClientProvider)),
);

class StaffAttendanceState {
  const StaffAttendanceState({required this.date, this.register, this.isSaving = false, this.savedAt});

  final DateTime date;
  final StaffAttendanceRegister? register;
  final bool isSaving;
  final DateTime? savedAt;

  String get dateString =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  StaffAttendanceState copyWith({
    DateTime? date,
    StaffAttendanceRegister? register,
    bool clearRegister = false,
    bool? isSaving,
    DateTime? savedAt,
  }) => StaffAttendanceState(
    date: date ?? this.date,
    register: clearRegister ? null : (register ?? this.register),
    isSaving: isSaving ?? this.isSaving,
    savedAt: savedAt ?? this.savedAt,
  );
}

final staffAttendanceControllerProvider = AsyncNotifierProvider<StaffAttendanceController, StaffAttendanceState>(
  StaffAttendanceController.new,
);

class StaffAttendanceController extends AsyncNotifier<StaffAttendanceState> {
  @override
  Future<StaffAttendanceState> build() async {
    var state = StaffAttendanceState(date: DateTime.now());
    return _loadRegister(state);
  }

  Future<StaffAttendanceState> _loadRegister(StaffAttendanceState current) async {
    final register = await ref.read(staffAttendanceRepositoryProvider).register(date: current.dateString);
    return current.copyWith(register: register);
  }

  Future<void> selectDate(DateTime date) async {
    final current = state.value;
    if (current == null) return;

    state = const AsyncLoading<StaffAttendanceState>();
    state = await AsyncValue.guard(() => _loadRegister(current.copyWith(date: date, clearRegister: true)));
  }

  void setStatus(int userId, AttendanceStatus status) {
    final current = state.value;
    if (current?.register == null) return;

    final updated = current!.register!.staff.map((s) => s.userId == userId ? s.copyWith(status: status) : s).toList();

    state = AsyncData(current.copyWith(
      register: StaffAttendanceRegister(
        date: current.register!.date,
        locked: current.register!.locked,
        lockedBy: current.register!.lockedBy,
        canUnlock: current.register!.canUnlock,
        staff: updated,
      ),
    ));
  }

  Future<void> save() async {
    final current = state.value;
    if (current?.register == null) return;

    state = AsyncData(current!.copyWith(isSaving: true));

    try {
      await ref.read(staffAttendanceRepositoryProvider).markAttendance(date: current.dateString, entries: current.register!.staff);
      final reloaded = await _loadRegister(current.copyWith(isSaving: false));
      state = AsyncData(reloaded.copyWith(savedAt: DateTime.now()));
    } catch (_) {
      state = AsyncData(current.copyWith(isSaving: false));
      rethrow;
    }
  }

  Future<void> unlock() async {
    final current = state.value;
    if (current?.register == null) return;

    state = AsyncData(current!.copyWith(isSaving: true));

    try {
      await ref.read(staffAttendanceRepositoryProvider).unlock(date: current.dateString);
      final reloaded = await _loadRegister(current.copyWith(isSaving: false));
      state = AsyncData(reloaded);
    } catch (_) {
      state = AsyncData(current.copyWith(isSaving: false));
      rethrow;
    }
  }
}
