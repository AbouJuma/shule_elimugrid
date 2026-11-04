import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/guardian_repository.dart';
import '../domain/guardian_model.dart';

final guardianRepositoryProvider = Provider<GuardianRepository>(
  (ref) => GuardianRepository(ref.watch(apiClientProvider)),
);

class GuardiansListState {
  const GuardiansListState({
    this.guardians = const [],
    this.page = 1,
    this.hasMore = true,
    this.search = '',
    this.isLoadingMore = false,
  });

  final List<GuardianModel> guardians;
  final int page;
  final bool hasMore;
  final String search;
  final bool isLoadingMore;

  GuardiansListState copyWith({
    List<GuardianModel>? guardians,
    int? page,
    bool? hasMore,
    String? search,
    bool? isLoadingMore,
  }) => GuardiansListState(
    guardians: guardians ?? this.guardians,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    search: search ?? this.search,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
  );
}

final guardiansListControllerProvider =
    AsyncNotifierProvider<GuardiansListController, GuardiansListState>(GuardiansListController.new);

/// Same shape as StudentsListController/InvoicesListController: search
/// filter plus infinite-scroll pagination.
class GuardiansListController extends AsyncNotifier<GuardiansListState> {
  @override
  Future<GuardiansListState> build() => _fetch(page: 1);

  Future<GuardiansListState> _fetch({
    required int page,
    String search = '',
    List<GuardianModel> existing = const [],
  }) async {
    final result = await ref.read(guardianRepositoryProvider).list(search: search, page: page);

    return GuardiansListState(
      guardians: [...existing, ...result.items],
      page: result.currentPage,
      hasMore: result.hasMore,
      search: search,
    );
  }

  Future<void> refresh() async {
    final current = state.value ?? const GuardiansListState();
    state = await AsyncValue.guard(() => _fetch(page: 1, search: current.search));
  }

  Future<void> setSearch(String search) async {
    state = await AsyncValue.guard(() => _fetch(page: 1, search: search));
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.isLoadingMore) return;

    state = AsyncData(current.copyWith(isLoadingMore: true));

    final next = await AsyncValue.guard(
      () => _fetch(page: current.page + 1, search: current.search, existing: current.guardians),
    );

    state = next.hasValue ? next : AsyncData(current.copyWith(isLoadingMore: false));
  }
}
