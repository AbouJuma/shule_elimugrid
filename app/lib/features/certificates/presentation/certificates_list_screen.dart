import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/presentation/auth_controller.dart';
import '../issue_forms.dart';
import 'certificates_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class CertificatesListScreen extends ConsumerStatefulWidget {
  const CertificatesListScreen({super.key});

  @override
  ConsumerState<CertificatesListScreen> createState() => _CertificatesListScreenState();
}

class _CertificatesListScreenState extends ConsumerState<CertificatesListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels > _scrollController.position.maxScrollExtent - 200) {
        ref.read(certificatesListControllerProvider.notifier).loadMore();
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
    final asyncState = ref.watch(certificatesListControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Certificates'))),
      floatingActionButton: (ref.watch(authControllerProvider).value?.user?.can('certificates.issue') ?? false)
          ? FloatingActionButton.extended(
              onPressed: () async {
                final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const IssueCertificateScreen()));
                if (saved == true) ref.read(certificatesListControllerProvider.notifier).refresh();
              },
              icon: const Icon(Icons.workspace_premium_outlined),
              label: Text(context.tr('Issue certificate')),
            )
          : null,
      body: switch (asyncState) {
        AsyncData(:final value) when value.certificates.isEmpty =>
          Center(child: Text(context.tr('No certificates issued yet.'), style: AppTypography.bodyMd)),
        AsyncData(:final value) => RefreshIndicator(
          onRefresh: () => ref.read(certificatesListControllerProvider.notifier).refresh(),
          child: ListView.separated(
            controller: _scrollController,
            itemCount: value.certificates.length + (value.hasMore ? 1 : 0),
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, index) {
              if (index >= value.certificates.length) {
                return const Padding(
                  padding: EdgeInsets.all(AppSpacing.md),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              final certificate = value.certificates[index];
              return ListTile(
                onTap: () => context.push('/home/certificates/${certificate.id}'),
                leading: CircleAvatar(
                  backgroundColor: certificate.isValid ? AppColors.gold100 : AppColors.error100,
                  child: Icon(
                    Icons.workspace_premium_outlined,
                    color: certificate.isValid ? AppColors.gold700 : AppColors.error600,
                  ),
                ),
                title: Text(certificate.title, style: AppTypography.bodyLg),
                subtitle: Text(
                  '${certificate.student?.name ?? certificate.certificateNumber} · ${certificate.certificateNumber}',
                  style: AppTypography.bodySm,
                ),
                trailing: _StatusBadge(status: certificate.status),
              );
            },
          ),
        ),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load certificates.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final isValid = status == 'valid' || status == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: isValid ? AppColors.green100 : AppColors.error100,
        borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
      ),
      child: Text(
        context.tr(status[0].toUpperCase() + status.substring(1)),
        style: AppTypography.bodySm.copyWith(color: isValid ? AppColors.green700 : AppColors.error600),
      ),
    );
  }
}
