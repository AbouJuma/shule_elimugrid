import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers.dart';
import '../../shared/forms/entity_form.dart';
import '../academics/academics_lookups.dart';

String _today() {
  final n = DateTime.now();

  return '${n.year.toString().padLeft(4, '0')}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
}

/// Issue a certificate to a student.
class IssueCertificateScreen extends ConsumerWidget {
  const IssueCertificateScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);

    return FutureBuilder<List<Map<String, dynamic>>>(
      future: api.rows('/certificate-templates').catchError((_) => <Map<String, dynamic>>[]),
      builder: (context, snap) {
        if (!snap.hasData) return const Scaffold(body: Center(child: CircularProgressIndicator()));

        return EntityFormScreen(
          title: 'Issue certificate',
          submitLabel: 'Issue certificate',
          fields: [
            FieldSpec(key: 'student_id', label: 'Student', kind: FieldKind.choice, required: true, search: (q) => searchStudents(api, q)),
            FieldSpec(key: 'certificate_template_id', label: 'Template', kind: FieldKind.choice, choices: [for (final t in snap.data!) FieldChoice('${t['id']}', t['name'] as String)]),
            const FieldSpec(key: 'title', label: 'Title', required: true, maxLength: 255, hint: 'e.g. Best in Mathematics'),
            const FieldSpec(key: 'achievement', label: 'What it is for (optional)', kind: FieldKind.multiline, maxLength: 2000),
            FieldSpec(key: 'issued_date', label: 'Date', kind: FieldKind.date, required: true, initial: _today(), lastDate: DateTime.now()),
          ],
          onSubmit: (v) async => api.post('/certificates', data: {
            'student_id': v['student_id'],
            'certificate_template_id': int.tryParse(v['certificate_template_id'] ?? ''),
            'title': v['title'],
            if ((v['achievement'] ?? '').isNotEmpty) 'achievement': v['achievement'],
            'issued_date': v['issued_date'],
          }),
        );
      },
    );
  }
}

/// Issue an ID card to a student.
class IssueIdCardScreen extends ConsumerWidget {
  const IssueIdCardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);

    return FutureBuilder<List<Map<String, dynamic>>>(
      future: api.rows('/id-card-templates').catchError((_) => <Map<String, dynamic>>[]),
      builder: (context, snap) {
        if (!snap.hasData) return const Scaffold(body: Center(child: CircularProgressIndicator()));

        return EntityFormScreen(
          title: 'Issue ID card',
          submitLabel: 'Issue ID card',
          fields: [
            FieldSpec(key: 'student_id', label: 'Student', kind: FieldKind.choice, required: true, search: (q) => searchStudents(api, q)),
            FieldSpec(key: 'id_card_template_id', label: 'Template', kind: FieldKind.choice, choices: [for (final t in snap.data!) FieldChoice('${t['id']}', t['name'] as String)]),
            FieldSpec(key: 'issued_date', label: 'Date', kind: FieldKind.date, required: true, initial: _today(), lastDate: DateTime.now()),
          ],
          onSubmit: (v) async => api.post('/id-cards', data: {
            'student_id': v['student_id'],
            'id_card_template_id': int.tryParse(v['id_card_template_id'] ?? ''),
            'issued_date': v['issued_date'],
          }),
        );
      },
    );
  }
}
