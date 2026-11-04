import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/i18n/app_localizations.dart';

enum AppButtonVariant { primary, gold, outline }

/// Consistent button across the app — screens should not call
/// ElevatedButton/OutlinedButton directly so sizing/variants stay uniform.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.isLoading = false,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final bool isLoading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final disabled = isLoading || onPressed == null;

    final child = isLoading
        ? const SizedBox(
            height: 20,
            width: 20,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: AppColors.white),
          )
        : Text(context.tr(label));

    final button = switch (variant) {
      AppButtonVariant.primary => ElevatedButton(
        onPressed: disabled ? null : onPressed,
        child: child,
      ),
      AppButtonVariant.gold => ElevatedButton(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.gold600,
          foregroundColor: AppColors.navy900,
        ),
        onPressed: disabled ? null : onPressed,
        child: child,
      ),
      AppButtonVariant.outline => OutlinedButton(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.navy700,
          side: const BorderSide(color: AppColors.navy700),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.md,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
          ),
        ),
        onPressed: disabled ? null : onPressed,
        child: child,
      ),
    };

    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
