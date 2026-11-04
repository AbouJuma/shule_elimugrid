import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/exam_models.dart';
import '../../auth/presentation/auth_controller.dart';
import 'exam_form_screens.dart';
import 'exams_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class ExamsListScreen extends ConsumerWidget {
  const ExamsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncExams = ref.watch(examsListProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Exams'))),
      floatingActionButton: (ref.watch(authControllerProvider).value?.user?.can('exams.manage') ?? false)
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ExamFormScreen())),
              icon: const Icon(Icons.add),
              label: Text(context.tr('Create exam')),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(examsListProvider.future),
        child: switch (asyncExams) {
          AsyncData(:final value) when value.isEmpty => ListView(
            children: [
              Padding(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Center(child: Text(context.tr('No exams found.'), style: AppTypography.bodyMd)),
              ),
            ],
          ),
          AsyncData(:final value) => ListView.separated(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
            itemCount: value.length,
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, index) => _ExamTile(exam: value[index]),
          ),
          AsyncError(:final error) => ListView(
            children: [
              Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Text('${context.tr('Could not load exams.')}\n$error', textAlign: TextAlign.center),
              ),
            ],
          ),
          _ => const Center(child: CircularProgressIndicator()),
        },
      ),
    );
  }
}

class _ExamTile extends StatelessWidget {
  const _ExamTile({required this.exam});

  final ExamModel exam;

  @override
  Widget build(BuildContext context) {
    final isPublished = exam.status == 'published';

    return ListTile(
      onTap: () => context.push('/home/exams/${exam.id}'),
      leading: CircleAvatar(
        backgroundColor: AppColors.navy100,
        child: const Icon(Icons.assignment_outlined, color: AppColors.navy700),
      ),
      title: Text(exam.name, style: AppTypography.bodyLg),
      subtitle: Text(exam.classNames.isEmpty ? '—' : exam.classNames, style: AppTypography.bodySm),
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm, vertical: 4),
        decoration: BoxDecoration(
          color: isPublished ? AppColors.green100 : AppColors.warning100,
          borderRadius: BorderRadius.circular(AppSpacing.radiusPill),
        ),
        child: Text(
          context.tr(exam.status[0].toUpperCase() + exam.status.substring(1)),
          style: AppTypography.bodySm.copyWith(
            color: isPublished ? AppColors.green700 : AppColors.warning600,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}
