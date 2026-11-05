import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';
import 'auth_controller.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../shared/widgets/language_toggle.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  String? _emailError;
  String? _passwordError;
  String? _generalError;
  bool _busy = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _emailError = null;
      _passwordError = null;
      _generalError = null;
    });

    setState(() => _busy = true);

    try {
      await ref.read(authControllerProvider.notifier).login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );
      // On success the router redirects to /home; nothing more to do here.
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _emailError = error.firstErrorFor('email');
        _passwordError = error.firstErrorFor('password');
        _generalError = (_emailError == null && _passwordError == null) ? error.message : null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _generalError = 'Something went wrong. Please try again.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = _busy;

    return Scaffold(
      backgroundColor: AppColors.navy900,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.xl),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Center(child: LanguageToggle()),
                      const SizedBox(height: AppSpacing.lg),
                      Text(context.tr('ElimuGrid'), style: AppTypography.displayLg),
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        context.tr('Sign in to your school workspace'),
                        style: AppTypography.bodyMd,
                      ),
                      const SizedBox(height: AppSpacing.xl),
                      if (_generalError != null) ...[
                        Container(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          decoration: BoxDecoration(
                            color: AppColors.error100,
                            borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                          ),
                          child: Text(
                            context.tr(_generalError!),
                            style: AppTypography.bodySm.copyWith(color: AppColors.error600),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                      ],
                      AppTextField(
                        label: context.tr('Email or Phone Number'),
                        controller: _emailController,
                        keyboardType: TextInputType.text,
                        autofillHints: const [AutofillHints.username],
                        textInputAction: TextInputAction.next,
                        errorText: _emailError,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      AppTextField(
                        label: 'Password',
                        controller: _passwordController,
                        isPassword: true,
                        autofillHints: const [AutofillHints.password],
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) => isLoading ? null : _submit(),
                        errorText: _passwordError,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      AppButton(
                        label: 'Sign in',
                        isLoading: isLoading,
                        onPressed: isLoading ? null : _submit,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
