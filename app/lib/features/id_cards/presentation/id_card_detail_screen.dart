import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:printing/printing.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../domain/id_card_model.dart';
import 'id_cards_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class IdCardDetailScreen extends ConsumerWidget {
  const IdCardDetailScreen({super.key, required this.idCardId});

  final int idCardId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncIdCard = ref.watch(idCardDetailProvider(idCardId));

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('ID Card'))),
      body: switch (asyncIdCard) {
        AsyncData(:final value) => _IdCardDetailBody(idCard: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load this ID card.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _IdCardDetailBody extends ConsumerStatefulWidget {
  const _IdCardDetailBody({required this.idCard});

  final IdCardModel idCard;

  @override
  ConsumerState<_IdCardDetailBody> createState() => _IdCardDetailBodyState();
}

class _IdCardDetailBodyState extends ConsumerState<_IdCardDetailBody> {
  bool _showPreview = false;

  @override
  Widget build(BuildContext context) {
    final idCard = widget.idCard;

    if (_showPreview) {
      return Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.sm),
            child: Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () => setState(() => _showPreview = false),
                icon: const Icon(Icons.arrow_back),
                label: Text(context.tr('Back to details')),
              ),
            ),
          ),
          Expanded(
            child: PdfPreview(
              build: (format) => ref.read(idCardRepositoryProvider).downloadPdf(idCard.pdfUrl),
              canChangeOrientation: false,
              canChangePageFormat: false,
              allowPrinting: true,
              allowSharing: true,
            ),
          ),
        ],
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Text(idCard.student?.name ?? idCard.cardNumber, style: AppTypography.headingLg),
        Text(idCard.cardNumber, style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary)),
        const SizedBox(height: AppSpacing.md),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (idCard.student != null)
                  _InfoRow(label: 'Admission No.', value: idCard.student!.admissionNumber),
                if (idCard.issuedDate != null) _InfoRow(label: 'Issued', value: idCard.issuedDate!),
                if (idCard.issuedBy != null) _InfoRow(label: 'Issued by', value: idCard.issuedBy!),
                _InfoRow(label: 'Status', value: idCard.status),
                if (!idCard.isActive && idCard.revocationReason != null)
                  _InfoRow(label: 'Reason', value: idCard.revocationReason!),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton(
          label: 'View / Download PDF',
          variant: AppButtonVariant.gold,
          onPressed: () => setState(() => _showPreview = true),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 120, child: Text(context.tr(label), style: AppTypography.bodySm)),
          Expanded(child: Text(context.tr(value), style: AppTypography.bodyMd)),
        ],
      ),
    );
  }
}
