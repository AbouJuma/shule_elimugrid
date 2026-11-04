import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/learning_repository.dart';
import '../domain/learning_models.dart';

final learningRepositoryProvider = Provider<LearningRepository>(
  (ref) => LearningRepository(ref.watch(apiClientProvider)),
);

final childLessonsProvider = FutureProvider.autoDispose.family<List<LessonItem>, String>(
  (ref, childId) => ref.watch(learningRepositoryProvider).lessons(childId),
);

final childHomeworkProvider = FutureProvider.autoDispose.family<List<HomeworkItem>, String>(
  (ref, childId) => ref.watch(learningRepositoryProvider).homework(childId),
);

final childOnlineExamsProvider = FutureProvider.autoDispose.family<List<OnlineExamSummary>, String>(
  (ref, childId) => ref.watch(learningRepositoryProvider).onlineExams(childId),
);
