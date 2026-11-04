import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../domain/guardian_model.dart';

/// Give a guardian a parent login, or reset the password of the one they have.
/// The temporary password is shown once (and also sent to the guardian by SMS).
/// Returns true when something changed, so the caller can refresh its list.
Future<bool> manageGuardianLogin(BuildContext context, WidgetRef ref, GuardianModel guardian) async {
  final api = ref.read(apiClientProvider);
  String? email;

  if (!guardian.hasLogin && (guardian.email == null || guardian.email!.isEmpty)) {
    email = await _askEmail(context);
    if (email == null) return false;
  } else if (guardian.hasLogin) {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.tr('Reset password')),
        content: Text(ctx.tr('A new temporary password will be created and the parent will be signed out everywhere.')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(ctx.tr('Reset password'))),
        ],
      ),
    );
    if (ok != true) return false;
  }

  try {
    final body = guardian.hasLogin
        ? await api.post('/guardians/${guardian.id}/account/reset')
        : await api.post('/guardians/${guardian.id}/account', data: {'email': ?email});

    if (!context.mounted) return true;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => _PasswordDialog(
        email: ((body['data'] as Map<String, dynamic>)['login_email'] ?? guardian.email ?? '') as String,
        password: body['temporary_password'] as String,
        smsSent: guardian.phone.isNotEmpty,
      ),
    );

    return true;
  } on ApiException catch (e) {
    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));

    return false;
  }
}

Future<String?> _askEmail(BuildContext context) {
  final controller = TextEditingController();

  return showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(ctx.tr('Parent login')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(ctx.tr('This guardian has no email address. The login is created with the email you enter here.')),
          const SizedBox(height: 12),
          TextField(controller: controller, keyboardType: TextInputType.emailAddress, autofocus: true, decoration: InputDecoration(labelText: ctx.tr('Email'))),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text(ctx.tr('Cancel'))),
        FilledButton(
          onPressed: () {
            final v = controller.text.trim();
            if (v.contains('@')) Navigator.pop(ctx, v);
          },
          child: Text(ctx.tr('Create parent login')),
        ),
      ],
    ),
  );
}

class _PasswordDialog extends StatelessWidget {
  const _PasswordDialog({required this.email, required this.password, required this.smsSent});

  final String email;
  final String password;
  final bool smsSent;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.tr('Parent login ready')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${context.tr('Email')}: $email'),
          const SizedBox(height: 8),
          Text(context.tr('Temporary password')),
          SelectableText(password, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, letterSpacing: 1)),
          const SizedBox(height: 12),
          Text(
            smsSent
                ? context.tr('It was also sent to the guardian by SMS. It is shown only now — the parent should change it after signing in.')
                : context.tr('It is shown only now — give it to the parent, who should change it after signing in.'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Clipboard.setData(ClipboardData(text: password)),
          child: Text(context.tr('Copy')),
        ),
        FilledButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Done'))),
      ],
    );
  }
}
