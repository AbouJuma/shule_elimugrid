import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/utils/currency.dart';
import '../../../shared/widgets/pdf_viewer_screen.dart';
import '../data/payslip_repository.dart';
import '../domain/payslip_models.dart';

final payslipRepositoryProvider = Provider<PayslipRepository>(
  (ref) => PayslipRepository(ref.watch(apiClientProvider)),
);

final myPayslipsProvider = FutureProvider.autoDispose<List<Payslip>>(
  (ref) => ref.watch(payslipRepositoryProvider).mine(),
);

final payslipDetailProvider = FutureProvider.autoDispose.family<Payslip, String>(
  (ref, id) => ref.watch(payslipRepositoryProvider).show(id),
);

class PayslipsScreen extends ConsumerWidget {
  const PayslipsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My payslips'))),
      body: switch (ref.watch(myPayslipsProvider)) {
        AsyncData(:final value) when value.isEmpty => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text(
              context.tr('No payslips yet. They appear here once payroll is approved.'),
              textAlign: TextAlign.center,
            ),
          ),
        ),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(myPayslipsProvider),
          child: ListView.separated(
            itemCount: value.length,
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, i) {
              final p = value[i];

              return ListTile(
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PayslipDetailScreen(id: p.id))),
                leading: CircleAvatar(
                  backgroundColor: p.isPaid ? AppColors.green100 : AppColors.warning100,
                  child: Icon(Icons.payments_outlined, color: p.isPaid ? AppColors.green700 : AppColors.warning600),
                ),
                title: Text(p.periodLabel, style: AppTypography.bodyLg),
                subtitle: Text(context.tr(p.isPaid ? 'Paid' : 'Awaiting payment'), style: AppTypography.bodySm),
                trailing: Text('${p.currency} ${formatMoney(p.net)}', style: AppTypography.headingSm),
              );
            },
          ),
        ),
        AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

String _money(String amount) => formatMoney(num.tryParse(amount) ?? 0);

class PayslipDetailScreen extends ConsumerWidget {
  const PayslipDetailScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Payslip'))),
      body: switch (ref.watch(payslipDetailProvider(id))) {
        AsyncData(:final value) => _Body(payslip: value),
        AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.payslip});

  final Payslip payslip;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final earnings = payslip.lines.where((l) => l.type == 'allowance').toList();
    final deductions = payslip.lines.where((l) => l.isDeduction).toList();
    final employer = payslip.lines.where((l) => l.isEmployer).toList();

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(color: AppColors.navy800, borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
          child: Column(children: [
            Text(payslip.periodLabel, style: AppTypography.bodyMd.copyWith(color: AppColors.textOnDark)),
            const SizedBox(height: AppSpacing.xs),
            Text('${payslip.currency} ${formatMoney(payslip.net)}', style: AppTypography.displayLg.copyWith(color: AppColors.white)),
            Text(context.tr('Net pay'), style: AppTypography.bodySm.copyWith(color: AppColors.textOnDark)),
          ]),
        ),
        const SizedBox(height: AppSpacing.md),
        Card(
          child: Column(children: [
            _Row(context.tr('Basic salary'), _money(payslip.basicSalary)),
            for (final l in earnings) _Row(l.name, formatMoney(l.value)),
            _Row(context.tr('Gross pay'), _money(payslip.grossPay), bold: true),
            for (final l in deductions) _Row(l.name, '- ${formatMoney(l.value)}', negative: true),
            _Row(context.tr('Total deductions'), '- ${_money(payslip.totalDeductions)}', bold: true, negative: true),
            _Row(context.tr('Net pay'), _money(payslip.netPay), bold: true),
          ]),
        ),
        if (employer.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          Card(
            child: Column(children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.xs),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(context.tr('Employer contributions (not deducted from your pay)'), style: AppTypography.bodySm),
                ),
              ),
              for (final l in employer) _Row(l.name, formatMoney(l.value)),
            ]),
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        FilledButton.icon(
          icon: const Icon(Icons.picture_as_pdf_outlined),
          label: Text(context.tr('View payslip (PDF)')),
          onPressed: () => openPdf(
            context,
            title: 'Payslip',
            loader: () => ref.read(payslipRepositoryProvider).downloadPdf(payslip.pdfUrl),
          ),
        ),
      ],
    );
  }
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value, {this.bold = false, this.negative = false});

  final String label;
  final String value;
  final bool bold;
  final bool negative;

  @override
  Widget build(BuildContext context) {
    final style = bold ? AppTypography.bodyMd.copyWith(fontWeight: FontWeight.w700) : AppTypography.bodyMd;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: 10),
      child: Row(children: [
        Expanded(child: Text(label, style: style)),
        Text(value, style: negative ? style.copyWith(color: AppColors.error600) : style),
      ]),
    );
  }
}
