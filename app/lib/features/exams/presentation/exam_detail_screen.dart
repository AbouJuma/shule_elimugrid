import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../auth/presentation/auth_controller.dart';
import 'exam_form_screens.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/exam_models.dart';
import 'exams_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class ExamDetailScreen extends ConsumerWidget {
  const ExamDetailScreen({super.key, required this.examId});

  final int examId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncExam = ref.watch(examDetailProvider(examId));

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Exam')),
        actions: [
          if ((ref.watch(authControllerProvider).value?.user?.can('exams.manage') ?? false) && asyncExam.hasValue)
            IconButton(
              tooltip: context.tr('Add subject'),
              icon: const Icon(Icons.playlist_add),
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AddExamSubjectScreen(exam: asyncExam.requireValue))),
            ),
        ],
      ),
      body: switch (asyncExam) {
        AsyncData(:final value) => _ExamDetailBody(exam: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load this exam.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _ExamDetailBody extends StatelessWidget {
  const _ExamDetailBody({required this.exam});

  final ExamModel exam;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Text(exam.name, style: AppTypography.headingLg),
        const SizedBox(height: AppSpacing.xs),
        Text(exam.classNames.isEmpty ? '—' : exam.classNames, style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary)),
        const SizedBox(height: AppSpacing.lg),
        Text(context.tr('Subjects'), style: AppTypography.label),
        const SizedBox(height: AppSpacing.sm),
        if (exam.subjects.isEmpty)
          Text(context.tr('No subjects have been added to this exam yet.'), style: AppTypography.bodyMd)
        else
          ...exam.subjects.map((subject) => Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: ListTile(
              title: Text(subject.name),
              subtitle: Text(context.tr('Max marks: {n}', {'n': '${subject.maxMarks}'})),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _openMarksEntry(context, subject),
            ),
          )),
      ],
    );
  }

  /// Marks entry always needs one specific class: skip the picker when the
  /// exam only runs for one, otherwise ask which of its classes this is for.
  Future<void> _openMarksEntry(BuildContext context, ExamSubject subject) async {
    int? classId = exam.classes.length == 1 ? exam.classes.first.id : null;

    classId ??= await showModalBottomSheet<int>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Text(sheetContext.tr('Enter marks for which class?'), style: AppTypography.label),
            ),
            for (final c in exam.classes)
              ListTile(title: Text(c.name), onTap: () => Navigator.of(sheetContext).pop(c.id)),
          ],
        ),
      ),
    );

    if (classId != null && context.mounted) {
      context.push('/home/exams/${exam.id}/subjects/${subject.id}/classes/$classId');
    }
  }
}
