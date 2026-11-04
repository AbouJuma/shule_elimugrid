import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../domain/student_model.dart';
import 'students_list_controller.dart';

final studentDetailProvider = FutureProvider.autoDispose.family<StudentModel, String>(
  (ref, id) => ref.watch(studentRepositoryProvider).show(id),
);
