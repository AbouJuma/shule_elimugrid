import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/student_repository.dart';
import '../domain/student_model.dart';

final studentRepositoryProvider = Provider<StudentRepository>(
  (ref) => StudentRepository(ref.watch(apiClientProvider)),
);

class StudentsListState {
  const StudentsListState({
    this.students = const [],
    this.page = 1,
    this.hasMore = true,
    this.total = 0,
    this.search = '',
    this.status = '',
    this.classId,
    this.isLoadingMore = false,
  });

  final List<StudentModel> students;
  final int page;
  final bool hasMore;
  final int total;
  final String search;
  final String status;
  final int? classId;
  final bool isLoadingMore;

  StudentsListState copyWith({
    List<StudentModel>? students,
    int? page,
    bool? hasMore,
    int? total,
    String? search,
    String? status,
    int? classId,
    bool clearClass = false,
    bool? isLoadingMore,
  }) => StudentsListState(
    students: students ?? this.students,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    total: total ?? this.total,
    search: search ?? this.search,
    status: status ?? this.status,
    classId: clearClass ? null : (classId ?? this.classId),
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
  );
}

final studentsListControllerProvider =
    AsyncNotifierProvider<StudentsListController, StudentsListState>(
      StudentsListController.new,
    );

/// Owns the students list screen's data: initial load, pull-to-refresh,
/// infinite-scroll pagination, and server-side search/status filtering
/// (mirrors the same filters the Bootstrap portal's Students table offers).
class StudentsListController extends AsyncNotifier<StudentsListState> {
  @override
  Future<StudentsListState> build() => _fetch(page: 1);

  Future<StudentsListState> _fetch({
    required int page,
    String search = '',
    String status = '',
    int? classId,
    List<StudentModel> existing = const [],
  }) async {
    final result = await ref.read(studentRepositoryProvider).list(
      search: search,
      status: status,
      classId: classId,
      page: page,
    );

    return StudentsListState(
      students: [...existing, ...result.items],
      page: result.currentPage,
      hasMore: result.hasMore,
      total: result.total,
      search: search,
      status: status,
      classId: classId,
    );
  }

  // Deliberately does not switch to AsyncLoading first: that would blank the
  // list mid-refresh/filter-change. The previous page's data stays on
  // screen (RefreshIndicator shows its own spinner for pull-to-refresh)
  // until the new page replaces it.

  Future<void> refresh() async {
    final current = state.value ?? const StudentsListState();
    state = await AsyncValue.guard(
      () => _fetch(page: 1, search: current.search, status: current.status, classId: current.classId),
    );
  }

  Future<void> setSearch(String search) async {
    final status = state.value?.status ?? '';
    state = await AsyncValue.guard(() => _fetch(page: 1, search: search, status: status, classId: state.value?.classId));
  }

  Future<void> setStatus(String status) async {
    final search = state.value?.search ?? '';
    state = await AsyncValue.guard(() => _fetch(page: 1, search: search, status: status, classId: state.value?.classId));
  }

  /// null clears the class filter.
  Future<void> setClass(int? classId) async {
    final search = state.value?.search ?? '';
    final status = state.value?.status ?? '';
    state = await AsyncValue.guard(() => _fetch(page: 1, search: search, status: status, classId: classId));
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.isLoadingMore) return;

    state = AsyncData(current.copyWith(isLoadingMore: true));

    final next = await AsyncValue.guard(
      () => _fetch(
        page: current.page + 1,
        search: current.search,
        status: current.status,
        classId: current.classId,
        existing: current.students,
      ),
    );

    state = next.hasValue ? next : AsyncData(current.copyWith(isLoadingMore: false));
  }
}
