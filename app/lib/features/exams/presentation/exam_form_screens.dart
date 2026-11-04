import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../shared/forms/entity_form.dart';
import '../../academics/academics_lookups.dart';
import '../domain/exam_models.dart';
import 'exams_controller.dart';

/// Create an exam for one or more classes (e.g. a shared "Mid-Term" for
/// Form 1 and Form 2). Subjects are added afterwards.
class ExamFormScreen extends ConsumerWidget {
  const ExamFormScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final years = ref.watch(yearOptionsProvider);
    final classes = ref.watch(classOptionsProvider);

    if (!years.hasValue || !classes.hasValue) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('Exam'))),
        body: Center(child: years.hasError || classes.hasError ? Text('${years.error ?? classes.error}') : const CircularProgressIndicator()),
      );
    }

    final current = years.requireValue.where((y) => y.isCurrent).firstOrNull ?? years.requireValue.firstOrNull;

    return EntityFormScreen(
      title: 'Create exam',
      submitLabel: 'Create exam',
      fields: [
        const FieldSpec(key: 'name', label: 'Exam name', required: true, maxLength: 150, hint: 'e.g. Mid-term'),
        FieldSpec(
          key: 'school_class_ids',
          label: 'Classes',
          kind: FieldKind.multi,
          required: true,
          choices: [for (final c in classes.requireValue) FieldChoice('${c.id}', c.name)],
        ),
        FieldSpec(
          key: 'academic_year_id',
          label: 'Academic year',
          kind: FieldKind.choice,
          required: true,
          initial: current != null ? '${current.id}' : null,
          choices: [for (final y in years.requireValue) FieldChoice('${y.id}', y.name)],
        ),
        FieldSpec(key: 'start_date', label: 'Start date', kind: FieldKind.date, firstDate: DateTime(2020), lastDate: DateTime.now().add(const Duration(days: 730))),
        FieldSpec(key: 'end_date', label: 'End date', kind: FieldKind.date, firstDate: DateTime(2020), lastDate: DateTime.now().add(const Duration(days: 730))),
        const FieldSpec(key: 'weight', label: 'Weight in the term result (1 = normal)', kind: FieldKind.decimal, initial: '1'),
      ],
      onSubmit: (v) async {
        await ref.read(apiClientProvider).post('/exams', data: {
          'name': v['name'],
          'school_class_ids': [for (final id in (v['school_class_ids'] ?? '').split(',')) if (id.isNotEmpty) int.parse(id)],
          'academic_year_id': int.tryParse(v['academic_year_id'] ?? ''),
          if ((v['start_date'] ?? '').isNotEmpty) 'start_date': v['start_date'],
          if ((v['end_date'] ?? '').isNotEmpty) 'end_date': v['end_date'],
          if ((v['weight'] ?? '').isNotEmpty) 'weight': double.tryParse(v['weight']!),
        });
        ref.invalidate(examsListProvider);
      },
    );
  }
}

/// Add a subject (with its maximum marks) to an exam.
class AddExamSubjectScreen extends ConsumerWidget {
  const AddExamSubjectScreen({super.key, required this.exam});

  final ExamModel exam;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final subjects = ref.watch(subjectOptionsProvider);

    if (!subjects.hasValue) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('Add subject'))),
        body: Center(child: subjects.hasError ? Text('${subjects.error}') : const CircularProgressIndicator()),
      );
    }

    final already = exam.subjects.map((s) => s.id).toSet();

    return EntityFormScreen(
      title: 'Add subject',
      submitLabel: 'Add subject',
      fields: [
        FieldSpec(
          key: 'subject_id',
          label: 'Subject',
          kind: FieldKind.choice,
          required: true,
          choices: [for (final s in subjects.requireValue.where((s) => !already.contains(s.id))) FieldChoice('${s.id}', s.name)],
        ),
        const FieldSpec(key: 'max_marks', label: 'Maximum marks', kind: FieldKind.number, required: true, initial: '100'),
      ],
      onSubmit: (v) async {
        await ref.read(apiClientProvider).post('/exams/${exam.id}/subjects', data: {
          'subject_id': int.tryParse(v['subject_id'] ?? ''),
          'max_marks': int.tryParse(v['max_marks'] ?? ''),
        });
        ref.invalidate(examDetailProvider(exam.id));
        ref.invalidate(examsListProvider);
      },
    );
  }
}
