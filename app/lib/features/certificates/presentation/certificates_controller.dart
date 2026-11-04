import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/certificate_repository.dart';
import '../domain/certificate_model.dart';

final certificateRepositoryProvider = Provider<CertificateRepository>(
  (ref) => CertificateRepository(ref.watch(apiClientProvider)),
);

class CertificatesListState {
  const CertificatesListState({
    this.certificates = const [],
    this.page = 1,
    this.hasMore = true,
    this.isLoadingMore = false,
  });

  final List<CertificateModel> certificates;
  final int page;
  final bool hasMore;
  final bool isLoadingMore;

  CertificatesListState copyWith({
    List<CertificateModel>? certificates,
    int? page,
    bool? hasMore,
    bool? isLoadingMore,
  }) => CertificatesListState(
    certificates: certificates ?? this.certificates,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
  );
}

final certificatesListControllerProvider =
    AsyncNotifierProvider<CertificatesListController, CertificatesListState>(
      CertificatesListController.new,
    );

/// Same infinite-scroll shape as GuardiansListController — read-only list,
/// no client-side filter (the API only supports a student_id filter,
/// which this screen doesn't need since it shows every issued certificate
/// across the school).
class CertificatesListController extends AsyncNotifier<CertificatesListState> {
  @override
  Future<CertificatesListState> build() => _fetch(page: 1);

  Future<CertificatesListState> _fetch({
    required int page,
    List<CertificateModel> existing = const [],
  }) async {
    final result = await ref.read(certificateRepositoryProvider).list(page: page);

    return CertificatesListState(
      certificates: [...existing, ...result.items],
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
      () => _fetch(page: current.page + 1, existing: current.certificates),
    );

    state = next.hasValue ? next : AsyncData(current.copyWith(isLoadingMore: false));
  }
}

final certificateDetailProvider = FutureProvider.autoDispose.family<CertificateModel, int>(
  (ref, id) => ref.watch(certificateRepositoryProvider).show(id),
);
