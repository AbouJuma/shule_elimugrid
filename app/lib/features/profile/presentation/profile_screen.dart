import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../shared/widgets/language_toggle.dart';
import 'change_password_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Profile'))),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Center(
              child: CircleAvatar(
                radius: 36,
                backgroundColor: AppColors.navy100,
                child: Text(
                  (user?.name.isNotEmpty ?? false) ? user!.name[0].toUpperCase() : '?',
                  style: AppTypography.displayLg.copyWith(color: AppColors.navy700),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Center(child: Text(user?.name ?? '', style: AppTypography.headingLg)),
            Center(
              child: Text(
                user?.email ?? '',
                style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Center(
              child: Wrap(
                spacing: AppSpacing.sm,
                children: (user?.roles ?? []).map((role) => Chip(
                  label: Text(context.tr(role)),
                  backgroundColor: AppColors.gold100,
                  labelStyle: AppTypography.bodySm.copyWith(color: AppColors.navy900),
                  side: BorderSide.none,
                )).toList(),
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.school_outlined, color: AppColors.navy700),
                    title: Text(context.tr('School')),
                    subtitle: Text(
                      user?.isPlatformUser == true ? context.tr('Platform (Super Admin)') : user?.school?.name ?? '—',
                    ),
                  ),
                  if (user?.phone != null)
                    ListTile(
                      leading: const Icon(Icons.phone_outlined, color: AppColors.navy700),
                      title: Text(context.tr('Phone')),
                      subtitle: Text(user!.phone!),
                    ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(context.tr('Language'), style: AppTypography.label),
            const SizedBox(height: AppSpacing.sm),
            const Center(child: LanguageToggle()),
            const SizedBox(height: AppSpacing.lg),
            OutlinedButton.icon(
              icon: const Icon(Icons.lock_outline),
              label: Text(context.tr('Change password')),
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChangePasswordScreen())),
            ),
            const SizedBox(height: AppSpacing.md),
            AppButton(
              label: 'Sign out',
              variant: AppButtonVariant.outline,
              onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            ),
          ],
        ),
      ),
    );
  }
}
