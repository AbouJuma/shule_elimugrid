import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/id_card_repository.dart';
import '../domain/id_card_model.dart';

final idCardRepositoryProvider = Provider<IdCardRepository>(
  (ref) => IdCardRepository(ref.watch(apiClientProvider)),
);

class IdCardsListState {
  const IdCardsListState({
    this.idCards = const [],
    this.page = 1,
    this.hasMore = true,
    this.isLoadingMore = false,
  });

  final List<IdCardModel> idCards;
  final int page;
  final bool hasMore;
  final bool isLoadingMore;

  IdCardsListState copyWith({
    List<IdCardModel>? idCards,
    int? page,
    bool? hasMore,
    bool? isLoadingMore,
  }) => IdCardsListState(
    idCards: idCards ?? this.idCards,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
  );
}

final idCardsListControllerProvider =
    AsyncNotifierProvider<IdCardsListController, IdCardsListState>(IdCardsListController.new);

class IdCardsListController extends AsyncNotifier<IdCardsListState> {
  @override
  Future<IdCardsListState> build() => _fetch(page: 1);

  Future<IdCardsListState> _fetch({
    required int page,
    List<IdCardModel> existing = const [],
  }) async {
    final result = await ref.read(idCardRepositoryProvider).list(page: page);

    return IdCardsListState(
      idCards: [...existing, ...result.items],
      page: result.currentPage,
      hasMore: result.hasMore,
    );
  }

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _fetch(page: 1));
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.isLoadingMore) return;

    state = AsyncData(current.copyWith(isLoadingMore: true));

    final next = await AsyncValue.guard(
      () => _fetch(page: current.page + 1, existing: current.idCards),
    );

    state = next.hasValue ? next : AsyncData(current.copyWith(isLoadingMore: false));
  }
}

final idCardDetailProvider = FutureProvider.autoDispose.family<IdCardModel, int>(
  (ref, id) => ref.watch(idCardRepositoryProvider).show(id),
);
