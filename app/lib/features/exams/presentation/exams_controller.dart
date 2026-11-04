import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/exam_repository.dart';
import '../domain/exam_models.dart';

final examRepositoryProvider = Provider<ExamRepository>(
  (ref) => ExamRepository(ref.watch(apiClientProvider)),
);

final examsListProvider = FutureProvider.autoDispose<List<ExamModel>>(
  (ref) => ref.watch(examRepositoryProvider).list(),
);

final examDetailProvider = FutureProvider.autoDispose.family<ExamModel, int>(
  (ref, examId) => ref.watch(examRepositoryProvider).show(examId),
);

/// Read-only fetch of one exam+subject's marks sheet. Edits are kept in
/// the marks-entry screen's own local widget state (see
/// MarksEntryScreen) rather than in a Riverpod notifier — Riverpod 3
/// dropped the straightforward hand-written "family AsyncNotifier"
/// pattern used elsewhere in this app (e.g. AttendanceController), and a
/// plain editable form with a local `Map<studentId, marks>` is simpler
/// and more idiomatic than working around that for a single
/// edit-then-save screen.
final marksRegisterProvider =
    FutureProvider.autoDispose.family<MarksRegister, ({int examId, int subjectId, int classId})>(
      (ref, params) => ref.watch(examRepositoryProvider).marksRegister(
        examId: params.examId,
        subjectId: params.subjectId,
        classId: params.classId,
      ),
    );
