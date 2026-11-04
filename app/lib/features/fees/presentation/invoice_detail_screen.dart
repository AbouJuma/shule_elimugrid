import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/utils/currency.dart';
import '../../../shared/widgets/app_button.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/invoice_models.dart';
import 'invoices_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class InvoiceDetailScreen extends ConsumerWidget {
  const InvoiceDetailScreen({super.key, required this.invoiceId});

  final String invoiceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncInvoice = ref.watch(invoiceDetailProvider(invoiceId));

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Invoice'))),
      body: switch (asyncInvoice) {
        AsyncData(:final value) => _InvoiceDetailBody(invoice: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load this invoice.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _InvoiceDetailBody extends ConsumerWidget {
  const _InvoiceDetailBody({required this.invoice});

  final InvoiceModel invoice;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;
    final canCollect = user?.can('fees.collect') ?? false;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Text(invoice.student?.name ?? invoice.invoiceNumber, style: AppTypography.headingLg),
        Text(invoice.invoiceNumber, style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary)),
        const SizedBox(height: AppSpacing.md),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              children: [
                _AmountRow(label: 'Total', value: invoice.totalAmount),
                _AmountRow(label: 'Paid', value: invoice.amountPaid),
                const Divider(),
                _AmountRow(label: 'Balance', value: invoice.balance, emphasize: true),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        if (invoice.items.isNotEmpty) ...[
          Text(context.tr('Items'), style: AppTypography.label),
          const SizedBox(height: AppSpacing.sm),
          ...invoice.items.map((item) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(item.name, style: AppTypography.bodyMd),
                Text(formatMoney(item.amount), style: AppTypography.bodyMd),
              ],
            ),
          )),
          const SizedBox(height: AppSpacing.md),
        ],
        if (invoice.payments.isNotEmpty) ...[
          Text(context.tr('Payments'), style: AppTypography.label),
          const SizedBox(height: AppSpacing.sm),
          ...invoice.payments.map((p) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: ListTile(
              title: Text(formatMoney(p.amount)),
              subtitle: Text('${p.method} · ${p.paidOn ?? ''} · ${p.receiptNumber}'),
            ),
          )),
          const SizedBox(height: AppSpacing.md),
        ],
        if (canCollect && invoice.balance > 0) _RecordPaymentForm(invoice: invoice),
      ],
    );
  }
}

class _AmountRow extends StatelessWidget {
  const _AmountRow({required this.label, required this.value, this.emphasize = false});

  final String label;
  final num value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final style = emphasize
        ? AppTypography.headingSm.copyWith(color: AppColors.navy900)
        : AppTypography.bodyMd;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(context.tr(label), style: style),
          Text(formatMoney(value), style: style),
        ],
      ),
    );
  }
}

const _paymentMethods = [
  ('cash', 'Cash'),
  ('bank_transfer', 'Bank transfer'),
  ('mobile_money', 'Mobile money'),
  ('other', 'Other'),
];

class _RecordPaymentForm extends ConsumerStatefulWidget {
  const _RecordPaymentForm({required this.invoice});

  final InvoiceModel invoice;

  @override
  ConsumerState<_RecordPaymentForm> createState() => _RecordPaymentFormState();
}

class _RecordPaymentFormState extends ConsumerState<_RecordPaymentForm> {
  final _amountController = TextEditingController();
  final _referenceController = TextEditingController();
  String _method = 'cash';
  DateTime _paidOn = DateTime.now();
  bool _isSaving = false;
  String? _error;

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amount = num.tryParse(_amountController.text.trim());
    if (amount == null || amount <= 0) {
      setState(() => _error = 'Enter a valid amount.');
      return;
    }

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await ref.read(invoiceRepositoryProvider).recordPayment(
        widget.invoice.id,
        amount: amount,
        method: _method,
        reference: _referenceController.text.trim(),
        paidOn: '${_paidOn.year.toString().padLeft(4, '0')}-${_paidOn.month.toString().padLeft(2, '0')}-${_paidOn.day.toString().padLeft(2, '0')}',
      );
      ref.invalidate(invoiceDetailProvider(widget.invoice.id));
      _amountController.clear();
      _referenceController.clear();
    } on ApiException catch (e) {
      setState(() => _error = e.firstErrorFor('amount') ?? e.message);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(context.tr('Record Payment'), style: AppTypography.headingSm),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: _amountController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(labelText: context.tr('Amount'), hintText: context.tr('Up to {max}', {'max': formatMoney(widget.invoice.balance)})),
            ),
            const SizedBox(height: AppSpacing.sm),
            DropdownButtonFormField<String>(
              initialValue: _method,
              decoration: InputDecoration(labelText: context.tr('Method')),
              items: _paymentMethods.map((m) => DropdownMenuItem(value: m.$1, child: Text(context.tr(m.$2)))).toList(),
              onChanged: (value) => setState(() => _method = value ?? _method),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: _referenceController,
              decoration: InputDecoration(labelText: context.tr('Reference (optional)')),
            ),
            const SizedBox(height: AppSpacing.sm),
            InkWell(
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _paidOn,
                  firstDate: DateTime(2020),
                  lastDate: DateTime.now(),
                );
                if (picked != null) setState(() => _paidOn = picked);
              },
              child: InputDecorator(
                decoration: InputDecoration(labelText: context.tr('Paid on')),
                child: Text(
                  '${_paidOn.year.toString().padLeft(4, '0')}-${_paidOn.month.toString().padLeft(2, '0')}-${_paidOn.day.toString().padLeft(2, '0')}',
                ),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(context.tr(_error!), style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
            ],
            const SizedBox(height: AppSpacing.md),
            AppButton(
              label: 'Record Payment',
              variant: AppButtonVariant.gold,
              isLoading: _isSaving,
              onPressed: _submit,
            ),
          ],
        ),
      ),
    );
  }
}
