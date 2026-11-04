import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:printing/printing.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../domain/certificate_model.dart';
import 'certificates_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class CertificateDetailScreen extends ConsumerWidget {
  const CertificateDetailScreen({super.key, required this.certificateId});

  final int certificateId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncCertificate = ref.watch(certificateDetailProvider(certificateId));

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Certificate'))),
      body: switch (asyncCertificate) {
        AsyncData(:final value) => _CertificateDetailBody(certificate: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load this certificate.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _CertificateDetailBody extends ConsumerStatefulWidget {
  const _CertificateDetailBody({required this.certificate});

  final CertificateModel certificate;

  @override
  ConsumerState<_CertificateDetailBody> createState() => _CertificateDetailBodyState();
}

class _CertificateDetailBodyState extends ConsumerState<_CertificateDetailBody> {
  bool _showPreview = false;

  @override
  Widget build(BuildContext context) {
    final certificate = widget.certificate;

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
              build: (format) => ref.read(certificateRepositoryProvider).downloadPdf(certificate.pdfUrl),
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
        Text(certificate.title, style: AppTypography.headingLg),
        Text(
          certificate.certificateNumber,
          style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary),
        ),
        const SizedBox(height: AppSpacing.md),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (certificate.student != null)
                  _InfoRow(label: 'Student', value: certificate.student!.name),
                if (certificate.template != null) _InfoRow(label: 'Template', value: certificate.template!),
                if (certificate.issuedDate != null)
                  _InfoRow(label: 'Issued', value: certificate.issuedDate!),
                if (certificate.issuedBy != null) _InfoRow(label: 'Issued by', value: certificate.issuedBy!),
                _InfoRow(label: 'Status', value: certificate.status),
                if (!certificate.isValid && certificate.revocationReason != null)
                  _InfoRow(label: 'Revocation reason', value: certificate.revocationReason!),
              ],
            ),
          ),
        ),
        if (certificate.achievement != null) ...[
          const SizedBox(height: AppSpacing.md),
          Text(context.tr('Achievement'), style: AppTypography.label),
          const SizedBox(height: AppSpacing.sm),
          Text(certificate.achievement!, style: AppTypography.bodyMd),
        ],
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
