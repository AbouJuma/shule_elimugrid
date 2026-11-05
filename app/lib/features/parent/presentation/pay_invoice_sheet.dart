import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/utils/currency.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';
import '../domain/parent_models.dart';
import 'parent_providers.dart';

/// Mirrors InitiateGatewayPaymentRequest's Rule::in(...) on the backend.
const _mobileMoneyProviders = ['Airtel', 'Tigo', 'Halopesa', 'Azampesa', 'Mpesa'];
const _bankProviders = ['CRDB', 'NMB'];

/// Opens the "pay this invoice" form as a bottom sheet, then (on success)
/// pushes the status screen that polls until the push is approved/declined.
void showPayInvoiceSheet(BuildContext context, {required String childId, required ChildInvoice invoice, required String currency}) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (sheetContext) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(sheetContext).viewInsets.bottom),
      child: _PayInvoiceForm(childId: childId, invoice: invoice, currency: currency),
    ),
  );
}

class _PayInvoiceForm extends ConsumerStatefulWidget {
  const _PayInvoiceForm({required this.childId, required this.invoice, required this.currency});

  final String childId;
  final ChildInvoice invoice;
  final String currency;

  @override
  ConsumerState<_PayInvoiceForm> createState() => _PayInvoiceFormState();
}

class _PayInvoiceFormState extends ConsumerState<_PayInvoiceForm> {
  String _provider = _mobileMoneyProviders.first;
  final _msisdnController = TextEditingController();
  final _amountController = TextEditingController();
  final _otpController = TextEditingController();
  final _accountNumberController = TextEditingController();
  bool _busy = false;
  String? _msisdnError;
  String? _amountError;
  String? _otpError;
  String? _accountError;
  String? _generalError;

  bool get _isBank => _bankProviders.contains(_provider);

  @override
  void dispose() {
    _msisdnController.dispose();
    _amountController.dispose();
    _otpController.dispose();
    _accountNumberController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _msisdnError = null;
      _amountError = null;
      _otpError = null;
      _accountError = null;
      _generalError = null;
    });

    try {
      final transaction = await ref
          .read(parentRepositoryProvider)
          .payInvoice(
            widget.childId,
            widget.invoice.id,
            msisdn: _msisdnController.text.trim(),
            provider: _provider,
            amount: _amountController.text.trim(),
            otp: _isBank ? _otpController.text.trim() : null,
            accountNumber: _isBank ? _accountNumberController.text.trim() : null,
          );

      if (!mounted) return;
      final navigator = Navigator.of(context);
      navigator.pop();
      navigator.push(MaterialPageRoute(builder: (_) => PaymentStatusScreen(childId: widget.childId, transactionId: transaction.id)));
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _msisdnError = error.firstErrorFor('msisdn');
        _amountError = error.firstErrorFor('amount');
        _otpError = error.firstErrorFor('otp');
        _accountError = error.firstErrorFor('account_number');
        _generalError = (_msisdnError == null && _amountError == null && _otpError == null && _accountError == null) ? error.message : null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _generalError = '$error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.lg),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(context.tr('Pay this invoice'), style: AppTypography.headingMd),
          const SizedBox(height: AppSpacing.xs),
          Text(
            '${context.tr('Outstanding balance')}: ${widget.currency} ${formatMoney(widget.invoice.balance)}',
            style: AppTypography.bodyMd,
          ),
          const SizedBox(height: AppSpacing.md),
          if (_generalError != null) ...[
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(color: AppColors.error100, borderRadius: BorderRadius.circular(AppSpacing.radiusSm)),
              child: Text(_generalError!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
            ),
            const SizedBox(height: AppSpacing.md),
          ],
          DropdownButtonFormField<String>(
            initialValue: _provider,
            decoration: InputDecoration(labelText: context.tr('Pay with')),
            items: [
              for (final p in _mobileMoneyProviders) DropdownMenuItem(value: p, child: Text(p)),
              for (final p in _bankProviders) DropdownMenuItem(value: p, child: Text('$p (${context.tr('Bank')})')),
            ],
            onChanged: _busy ? null : (value) => setState(() => _provider = value ?? _provider),
          ),
          const SizedBox(height: AppSpacing.md),
          AppTextField(
            label: _isBank ? 'Phone number registered with your bank' : 'Phone number to receive the payment prompt',
            controller: _msisdnController,
            keyboardType: TextInputType.phone,
            errorText: _msisdnError,
          ),
          const SizedBox(height: AppSpacing.md),
          AppTextField(
            label: 'Amount — leave blank to pay the full balance',
            controller: _amountController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            errorText: _amountError,
          ),
          if (_isBank) ...[
            const SizedBox(height: AppSpacing.md),
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(color: AppColors.gold100, borderRadius: BorderRadius.circular(AppSpacing.radiusSm)),
              child: Text(
                context.tr(
                  _provider == 'CRDB'
                      ? 'Dial *150*03# on your own phone first to get an OTP from CRDB, then enter it below.'
                      : 'Dial *150*66# on your own phone first to get an OTP from NMB, then enter it below.',
                ),
                style: AppTypography.bodySm,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: 'Your bank account number', controller: _accountNumberController, errorText: _accountError),
            const SizedBox(height: AppSpacing.md),
            AppTextField(label: 'OTP from your bank', controller: _otpController, keyboardType: TextInputType.number, errorText: _otpError),
          ],
          const SizedBox(height: AppSpacing.lg),
          AppButton(label: 'Pay now', isLoading: _busy, onPressed: _busy ? null : _submit),
        ],
      ),
    );
  }
}

/// Polls the transaction's status (see paymentStatusProvider) until it's
/// completed or failed — the guardian is approving/declining a push on
/// their own phone, or has just entered an OTP, somewhere outside this screen.
class PaymentStatusScreen extends ConsumerWidget {
  const PaymentStatusScreen({super.key, required this.childId, required this.transactionId});

  final String childId;
  final String transactionId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncTransaction = ref.watch(paymentStatusProvider((childId: childId, transactionId: transactionId)));

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Payment status')), automaticallyImplyLeading: false),
      body: Center(
        child: switch (asyncTransaction) {
          AsyncData(:final value) => _StatusBody(transaction: value),
          AsyncError(:final error) => Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not check the payment status.')}\n$error', textAlign: TextAlign.center),
          ),
          _ => const CircularProgressIndicator(),
        },
      ),
    );
  }
}

class _StatusBody extends StatelessWidget {
  const _StatusBody({required this.transaction});

  final GatewayTransaction transaction;

  @override
  Widget build(BuildContext context) {
    final (icon, colour, title) = switch (transaction.status) {
      'completed' => (Icons.check_circle, AppColors.green700, 'Payment received'),
      'failed' => (Icons.error, AppColors.error600, 'Payment failed'),
      'pending' => (Icons.hourglass_top, AppColors.gold600, 'Waiting for your approval…'),
      _ => (Icons.send_outlined, AppColors.gold600, 'Sending request…'),
    };

    return Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 72, color: colour),
          const SizedBox(height: AppSpacing.md),
          Text(context.tr(title), style: AppTypography.headingMd, textAlign: TextAlign.center),
          const SizedBox(height: AppSpacing.sm),
          if (!transaction.isSettled)
            Text(context.tr('Check your phone to approve the payment.'), style: AppTypography.bodyMd, textAlign: TextAlign.center),
          if (transaction.status == 'failed' && transaction.failureReason != null)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xs),
              child: Text(transaction.failureReason!, style: AppTypography.bodyMd.copyWith(color: AppColors.error600), textAlign: TextAlign.center),
            ),
          if (transaction.status == 'completed' && transaction.receiptNumber != null)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xs),
              child: Text('${context.tr('Receipt')}: ${transaction.receiptNumber}', style: AppTypography.bodyMd),
            ),
          const SizedBox(height: AppSpacing.lg),
          if (transaction.isSettled) AppButton(label: 'Done', expand: false, onPressed: () => Navigator.of(context).pop()),
        ],
      ),
    );
  }
}
