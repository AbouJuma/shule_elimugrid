import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../shared/forms/entity_form.dart';
import '../../parent/presentation/parent_providers.dart';

/// Change your own password. Other devices are signed out; this one stays.
class ChangePasswordScreen extends ConsumerWidget {
  const ChangePasswordScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return EntityFormScreen(
      title: 'Change password',
      fields: [
        const FieldSpec(key: 'current_password', label: 'Current password', kind: FieldKind.password, required: true),
        const FieldSpec(key: 'password', label: 'New password', kind: FieldKind.password, required: true, hint: 'At least 8 characters'),
        const FieldSpec(key: 'password_confirmation', label: 'Confirm new password', kind: FieldKind.password, required: true),
      ],
      onSubmit: (v) async {
        if (v['password'] != v['password_confirmation']) {
          throw _Mismatch();
        }

        await ref.read(parentRepositoryProvider).changePassword(current: v['current_password']!, password: v['password']!, confirmation: v['password_confirmation']!);
        if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Password changed.'))));
      },
    );
  }
}

class _Mismatch implements Exception {
  @override
  String toString() => AppLocalizations.translate('The two passwords do not match.');
}
