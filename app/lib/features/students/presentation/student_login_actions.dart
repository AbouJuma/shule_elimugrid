import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../domain/student_model.dart';

/// Give a student their own login (Student role, sees only their own results,
/// timetable, fees and attendance), or reset the password of the one they
/// have. A student usually has no email or phone of their own, so the school
/// supplies an email and the temporary password is shown once here — there is
/// no SMS for it, unlike a parent's.
Future<bool> manageStudentLogin(BuildContext context, WidgetRef ref, StudentModel student) async {
  final api = ref.read(apiClientProvider);
  String? email;

  if (!student.hasLogin) {
    email = await _askEmail(context, student.fullName);
    if (email == null) return false;
  } else {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.tr('Reset password')),
        content: Text(ctx.tr('A new temporary password will be created and the student will be signed out everywhere.')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(ctx.tr('Reset password'))),
        ],
      ),
    );
    if (ok != true) return false;
  }

  try {
    final body = student.hasLogin
        ? await api.post('/students/${student.id}/account/reset')
        : await api.post('/students/${student.id}/account', data: {'email': email});

    if (!context.mounted) return true;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => _PasswordDialog(email: ((body['data'] as Map<String, dynamic>)['login_email'] ?? email ?? '') as String, password: body['temporary_password'] as String),
    );

    return true;
  } on ApiException catch (e) {
    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));

    return false;
  }
}

Future<String?> _askEmail(BuildContext context, String studentName) {
  final controller = TextEditingController();

  return showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(ctx.tr('Student login')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(ctx.tr('An email is needed to create {name}\'s login — a family address works.', {'name': studentName})),
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
          child: Text(ctx.tr('Create student login')),
        ),
      ],
    ),
  );
}

class _PasswordDialog extends StatelessWidget {
  const _PasswordDialog({required this.email, required this.password});

  final String email;
  final String password;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.tr('Student login ready')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${context.tr('Email')}: $email'),
          const SizedBox(height: 8),
          Text(context.tr('Temporary password')),
          SelectableText(password, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, letterSpacing: 1)),
          const SizedBox(height: 12),
          Text(context.tr('It is shown only now — give it to the student, who should change it after signing in.')),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Clipboard.setData(ClipboardData(text: password)), child: Text(context.tr('Copy'))),
        FilledButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Done'))),
      ],
    );
  }
}
