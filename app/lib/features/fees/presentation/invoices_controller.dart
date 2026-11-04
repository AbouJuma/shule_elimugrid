import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/invoice_repository.dart';
import '../domain/invoice_models.dart';

final invoiceRepositoryProvider = Provider<InvoiceRepository>(
  (ref) => InvoiceRepository(ref.watch(apiClientProvider)),
);

class InvoicesListState {
  const InvoicesListState({
    this.invoices = const [],
    this.page = 1,
    this.hasMore = true,
    this.status = '',
    this.isLoadingMore = false,
  });

  final List<InvoiceModel> invoices;
  final int page;
  final bool hasMore;
  final String status;
  final bool isLoadingMore;

  InvoicesListState copyWith({
    List<InvoiceModel>? invoices,
    int? page,
    bool? hasMore,
    String? status,
    bool? isLoadingMore,
  }) => InvoicesListState(
    invoices: invoices ?? this.invoices,
    page: page ?? this.page,
    hasMore: hasMore ?? this.hasMore,
    status: status ?? this.status,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
  );
}

final invoicesListControllerProvider =
    AsyncNotifierProvider<InvoicesListController, InvoicesListState>(InvoicesListController.new);

/// Same shape as StudentsListController: server-side status filter plus
/// infinite-scroll pagination, edits kept purely local until a fetch
/// replaces them.
class InvoicesListController extends AsyncNotifier<InvoicesListState> {
  @override
  Future<InvoicesListState> build() => _fetch(page: 1);

  Future<InvoicesListState> _fetch({
    required int page,
    String status = '',
    List<InvoiceModel> existing = const [],
  }) async {
    final result = await ref.read(invoiceRepositoryProvider).list(status: status, page: page);

    return InvoicesListState(
      invoices: [...existing, ...result.items],
      page: result.currentPage,
      hasMore: result.hasMore,
      status: status,
    );
  }

  Future<void> refresh() async {
    final current = state.value ?? const InvoicesListState();
    state = await AsyncValue.guard(() => _fetch(page: 1, status: current.status));
  }

  Future<void> setStatus(String status) async {
    state = await AsyncValue.guard(() => _fetch(page: 1, status: status));
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || current.isLoadingMore) return;

    state = AsyncData(current.copyWith(isLoadingMore: true));

    final next = await AsyncValue.guard(
      () => _fetch(page: current.page + 1, status: current.status, existing: current.invoices),
    );

    state = next.hasValue ? next : AsyncData(current.copyWith(isLoadingMore: false));
  }
}

final invoiceDetailProvider = FutureProvider.autoDispose.family<InvoiceModel, String>(
  (ref, id) => ref.watch(invoiceRepositoryProvider).show(id),
);
