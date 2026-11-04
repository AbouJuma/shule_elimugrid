import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/attendance_repository.dart';
import '../domain/attendance_models.dart';

final attendanceRepositoryProvider = Provider<AttendanceRepository>(
  (ref) => AttendanceRepository(ref.watch(apiClientProvider)),
);

class AttendanceState {
  const AttendanceState({
    required this.classes,
    this.selectedClassId,
    this.selectedSubjectId,
    required this.date,
    this.register,
    this.isSaving = false,
    this.savedAt,
  });

  final List<AttendanceClass> classes;
  final int? selectedClassId;
  // Null = the "By Class" tab (whole-day register); set = a "By Subject" tab.
  final int? selectedSubjectId;
  final DateTime date;
  final AttendanceRegister? register;
  final bool isSaving;
  final DateTime? savedAt;

  List<AttendanceSubject> get subjectsForSelectedClass =>
      classes.firstWhere((c) => c.id == selectedClassId, orElse: () => const AttendanceClass(id: -1, name: '')).subjects;

  String get dateString =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  AttendanceState copyWith({
    List<AttendanceClass>? classes,
    int? selectedClassId,
    int? selectedSubjectId,
    bool clearSubject = false,
    DateTime? date,
    AttendanceRegister? register,
    bool clearRegister = false,
    bool? isSaving,
    DateTime? savedAt,
  }) => AttendanceState(
    classes: classes ?? this.classes,
    selectedClassId: selectedClassId ?? this.selectedClassId,
    selectedSubjectId: clearSubject ? null : (selectedSubjectId ?? this.selectedSubjectId),
    date: date ?? this.date,
    register: clearRegister ? null : (register ?? this.register),
    isSaving: isSaving ?? this.isSaving,
    savedAt: savedAt ?? this.savedAt,
  );
}

final attendanceControllerProvider = AsyncNotifierProvider<AttendanceController, AttendanceState>(
  AttendanceController.new,
);

/// Owns the register screen's state: available classes, the selected
/// class/date, the fetched register, and locally-edited (not-yet-saved)
/// statuses. Statuses are edited purely in memory until "Save" — nothing
/// is sent to the server per-tap, mirroring how a paper register works.
class AttendanceController extends AsyncNotifier<AttendanceState> {
  @override
  Future<AttendanceState> build() async {
    final classes = await ref.read(attendanceRepositoryProvider).classes();
    var state = AttendanceState(classes: classes, date: DateTime.now());

    if (classes.isNotEmpty) {
      state = state.copyWith(selectedClassId: classes.first.id);
      state = await _loadRegister(state);
    }

    return state;
  }

  Future<AttendanceState> _loadRegister(AttendanceState current) async {
    if (current.selectedClassId == null) return current;

    final register = await ref.read(attendanceRepositoryProvider).register(
      classId: current.selectedClassId!,
      date: current.dateString,
      subjectId: current.selectedSubjectId,
    );

    return current.copyWith(register: register);
  }

  Future<void> selectClass(int classId) async {
    final current = state.value;
    if (current == null) return;

    state = const AsyncLoading<AttendanceState>();
    // A different class may not offer the previously-picked subject — back
    // to "By Class" rather than risk a stale/invalid subject tab.
    state = await AsyncValue.guard(
      () => _loadRegister(current.copyWith(selectedClassId: classId, clearSubject: true, clearRegister: true)),
    );
  }

  Future<void> selectSubject(int? subjectId) async {
    final current = state.value;
    if (current == null) return;

    state = const AsyncLoading<AttendanceState>();
    state = await AsyncValue.guard(
      () => _loadRegister(AttendanceState(
        classes: current.classes,
        selectedClassId: current.selectedClassId,
        selectedSubjectId: subjectId,
        date: current.date,
      )),
    );
  }

  Future<void> selectDate(DateTime date) async {
    final current = state.value;
    if (current == null) return;

    state = const AsyncLoading<AttendanceState>();
    state = await AsyncValue.guard(
      () => _loadRegister(current.copyWith(date: date, clearRegister: true)),
    );
  }

  void setStatus(int studentId, AttendanceStatus status) {
    final current = state.value;
    if (current?.register == null) return;

    final updatedStudents = current!.register!.students
        .map((s) => s.studentId == studentId ? s.copyWith(status: status) : s)
        .toList();

    state = AsyncData(current.copyWith(
      register: AttendanceRegister(
        classId: current.register!.classId,
        className: current.register!.className,
        date: current.register!.date,
        subjectId: current.register!.subjectId,
        locked: current.register!.locked,
        lockedBy: current.register!.lockedBy,
        canUnlock: current.register!.canUnlock,
        students: updatedStudents,
      ),
    ));
  }

  Future<void> save() async {
    final current = state.value;
    if (current?.register == null || current!.selectedClassId == null) return;

    state = AsyncData(current.copyWith(isSaving: true));

    try {
      await ref.read(attendanceRepositoryProvider).markAttendance(
        classId: current.selectedClassId!,
        date: current.dateString,
        subjectId: current.selectedSubjectId,
        entries: current.register!.students,
      );

      // Reload: the register just locked itself, and the screen needs to
      // reflect that (disabled statuses, an Unlock button instead of Save).
      final reloaded = await _loadRegister(current.copyWith(isSaving: false));
      state = AsyncData(reloaded.copyWith(savedAt: DateTime.now()));
    } catch (_) {
      state = AsyncData(current.copyWith(isSaving: false));
      rethrow;
    }
  }

  Future<void> unlock() async {
    final current = state.value;
    if (current?.register == null || current!.selectedClassId == null) return;

    state = AsyncData(current.copyWith(isSaving: true));

    try {
      await ref.read(attendanceRepositoryProvider).unlock(
        classId: current.selectedClassId!,
        date: current.dateString,
        subjectId: current.selectedSubjectId,
      );

      final reloaded = await _loadRegister(current.copyWith(isSaving: false));
      state = AsyncData(reloaded);
    } catch (_) {
      state = AsyncData(current.copyWith(isSaving: false));
      rethrow;
    }
  }
}
