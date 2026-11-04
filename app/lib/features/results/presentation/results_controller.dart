import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/results_repository.dart';
import '../domain/results_models.dart';

final resultsRepositoryProvider = Provider<ResultsRepository>(
  (ref) => ResultsRepository(ref.watch(apiClientProvider)),
);

final resultTermsProvider = FutureProvider.autoDispose<List<ResultTerm>>(
  (ref) => ref.watch(resultsRepositoryProvider).terms(),
);

typedef ResultsQuery = ({int termId, int classId});

final classResultsProvider = FutureProvider.autoDispose.family<ClassResults, ResultsQuery>(
  (ref, q) => ref.watch(resultsRepositoryProvider).forClass(termId: q.termId, classId: q.classId),
);
