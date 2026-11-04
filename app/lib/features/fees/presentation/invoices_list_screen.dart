import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/utils/currency.dart';
import '../domain/invoice_models.dart';
import '../../auth/presentation/auth_controller.dart';
import '../fee_setup_screens.dart';
import 'invoices_controller.dart';
import '../../../core/i18n/app_localizations.dart';

const _statusOptions = [
  ('', 'All statuses'),
  ('unpaid', 'Unpaid'),
  ('partial', 'Partial'),
  ('paid', 'Paid'),
];

class InvoicesListScreen extends ConsumerStatefulWidget {
  const InvoicesListScreen({super.key});

  @override
  ConsumerState<InvoicesListScreen> createState() => _InvoicesListScreenState();
}

class _InvoicesListScreenState extends ConsumerState<InvoicesListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 200) {
        ref.read(invoicesListControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final asyncState = ref.watch(invoicesListControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Invoices')),
        actions: [
          if (ref.watch(authControllerProvider).value?.user?.can('fees.manage') ?? false)
            IconButton(
              tooltip: context.tr('Fee structures'),
              icon: const Icon(Icons.request_quote_outlined),
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const FeeStructuresScreen())),
            ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.filter_list),
            tooltip: context.tr('Filter by status'),
            onSelected: (status) => ref.read(invoicesListControllerProvider.notifier).setStatus(status),
            itemBuilder: (context) =>
                _statusOptions.map((o) => PopupMenuItem(value: o.$1, child: Text(context.tr(o.$2)))).toList(),
          ),
        ],
      ),
      body: switch (asyncState) {
        AsyncData(:final value) when value.invoices.isEmpty =>
          Center(child: Text(context.tr('No invoices found.'), style: AppTypography.bodyMd)),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: () => ref.read(invoicesListControllerProvider.notifier).refresh(),
          child: ListView.separated(
            controller: _scrollController,
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
            itemCount: value.invoices.length + (value.hasMore ? 1 : 0),
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, index) {
              if (index >= value.invoices.length) {
                return const Padding(
                  padding: EdgeInsets.all(AppSpacing.md),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              return _InvoiceTile(invoice: value.invoices[index]);
            },
          ),
        ),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load invoices.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _InvoiceTile extends StatelessWidget {
  const _InvoiceTile({required this.invoice});

  final InvoiceModel invoice;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: () => context.push('/home/fees/${invoice.id}'),
      leading: CircleAvatar(
        backgroundColor: AppColors.navy100,
        child: const Icon(Icons.receipt_long_outlined, color: AppColors.navy700),
      ),
      title: Text(invoice.student?.name ?? invoice.invoiceNumber, style: AppTypography.bodyLg),
      subtitle: Text(
        '${invoice.invoiceNumber} · Balance: ${formatMoney(invoice.balance)}',
        style: AppTypography.bodySm,
      ),
      trailing: _StatusChip(status: invoice.status),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (status) {
      'paid' => (AppColors.green100, AppColors.green700),
      'partial' => (AppColors.warning100, AppColors.warning600),
      _ => (AppColors.error100, AppColors.error600),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(AppSpacing.radiusPill)),
      child: Text(
        context.tr(status[0].toUpperCase() + status.substring(1)),
        style: AppTypography.bodySm.copyWith(color: fg, fontWeight: FontWeight.w600),
      ),
    );
  }
}
