import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../domain/learning_models.dart';
import 'learning_providers.dart';

/// One child's Learning Hub: lessons to watch, homework posted, and online
/// exams to take — a nested tab bar inside the parent/student's existing
/// per-child tab (Bus/Fees/Attendance/Results/Timetable), same shape one
/// level down.
class LearningHubTab extends StatelessWidget {
  const LearningHubTab({super.key, required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Column(
        children: [
          Material(
            color: AppColors.surface,
            child: TabBar(
              tabs: [
                Tab(text: context.tr('Lessons')),
                Tab(text: context.tr('Homework')),
                Tab(text: context.tr('Online Exams')),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(children: [
              _LessonsSection(childId: childId),
              _HomeworkSection(childId: childId),
              _OnlineExamsSection(childId: childId),
            ]),
          ),
        ],
      ),
    );
  }
}

Future<void> _openLink(BuildContext context, String url) async {
  final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr("Couldn't open that link."))));
  }
}

// ---- Lessons ----------------------------------------------------------------------------------------------------------

class _LessonsSection extends ConsumerWidget {
  const _LessonsSection({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childLessonsProvider(childId))) {
      AsyncData(:final value) when value.isEmpty => _EmptyMessage(text: context.tr('No lessons posted yet.')),
      AsyncData(:final value) => RefreshIndicator(
        onRefresh: () async => ref.invalidate(childLessonsProvider(childId)),
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.md),
          itemCount: value.length,
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, i) => _LessonCard(lesson: value[i]),
        ),
      ),
      AsyncError(:final error) => _EmptyMessage(text: '${context.tr('Could not load lessons.')}\n$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _LessonCard extends StatelessWidget {
  const _LessonCard({required this.lesson});

  final LessonItem lesson;

  @override
  Widget build(BuildContext context) {
    final hasLink = lesson.videoUrl != null;
    final hasUpload = lesson.videoStreamUrl != null;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(lesson.title, style: AppTypography.bodyLg),
            if (lesson.subject != null) Text(lesson.subject!, style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary)),
            if (lesson.description != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(lesson.description!, style: AppTypography.bodyMd),
            ],
            const SizedBox(height: AppSpacing.sm),
            if (hasLink)
              AppButton(label: 'Watch video', variant: AppButtonVariant.outline, onPressed: () => _openLink(context, lesson.videoUrl!))
            else if (hasUpload)
              // No in-app player for a school-uploaded file yet — opening it
              // externally would drop the login the file needs, so this is
              // pointed at the web portal instead (see FILE_PLAN's honest gaps).
              Text(context.tr('Video uploaded by the school — open the web portal to watch it.'), style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }
}

// ---- Homework -----------------------------------------------------------------------------------------------------------

class _HomeworkSection extends ConsumerWidget {
  const _HomeworkSection({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childHomeworkProvider(childId))) {
      AsyncData(:final value) when value.isEmpty => _EmptyMessage(text: context.tr('No homework posted yet.')),
      AsyncData(:final value) => RefreshIndicator(
        onRefresh: () async => ref.invalidate(childHomeworkProvider(childId)),
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.md),
          itemCount: value.length,
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, i) => _HomeworkCard(homework: value[i]),
        ),
      ),
      AsyncError(:final error) => _EmptyMessage(text: '${context.tr('Could not load homework.')}\n$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _HomeworkCard extends StatelessWidget {
  const _HomeworkCard({required this.homework});

  final HomeworkItem homework;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(child: Text(homework.title, style: AppTypography.bodyLg)),
                if (homework.dueDate != null)
                  Text(context.tr('Due {date}', {'date': homework.dueDate!}), style: AppTypography.bodySm.copyWith(color: AppColors.warning600)),
              ],
            ),
            if (homework.subject != null) Text(homework.subject!, style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary)),
            if (homework.instructions != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(homework.instructions!, style: AppTypography.bodyMd),
            ],
            if (homework.attachmentUrl != null) ...[
              const SizedBox(height: AppSpacing.sm),
              AppButton(label: 'Open attachment', variant: AppButtonVariant.outline, onPressed: () => _openLink(context, homework.attachmentUrl!)),
            ],
          ],
        ),
      ),
    );
  }
}

// ---- Online exams ---------------------------------------------------------------------------------------------------------

class _OnlineExamsSection extends ConsumerWidget {
  const _OnlineExamsSection({required this.childId});

  final String childId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(childOnlineExamsProvider(childId))) {
      AsyncData(:final value) when value.isEmpty => _EmptyMessage(text: context.tr('No online exams available yet.')),
      AsyncData(:final value) => RefreshIndicator(
        onRefresh: () async => ref.invalidate(childOnlineExamsProvider(childId)),
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.md),
          itemCount: value.length,
          separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, i) => _ExamCard(childId: childId, exam: value[i]),
        ),
      ),
      AsyncError(:final error) => _EmptyMessage(text: '${context.tr('Could not load online exams.')}\n$error'),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _ExamCard extends ConsumerWidget {
  const _ExamCard({required this.childId, required this.exam});

  final String childId;
  final OnlineExamSummary exam;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(exam.title, style: AppTypography.bodyLg),
            Text(
              [
                if (exam.subject != null) exam.subject!,
                context.tr('{n} question(s)', {'n': '${exam.questionCount}'}),
                exam.isGraded ? context.tr('Graded — one attempt') : context.tr('Practice — unlimited attempts'),
              ].join(' · '),
              style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary),
            ),
            if (exam.lastScore != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(
                context.tr('Last score: {score}/{max}', {'score': '${exam.lastScore!.score}', 'max': '${exam.lastScore!.maxScore}'}),
                style: AppTypography.bodyMd.copyWith(color: AppColors.green700, fontWeight: FontWeight.w600),
              ),
            ],
            const SizedBox(height: AppSpacing.sm),
            AppButton(
              label: exam.completed ? 'Completed' : (exam.inProgress ? 'Continue' : 'Start'),
              variant: AppButtonVariant.gold,
              onPressed: exam.completed
                  ? null
                  : () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => ExamTakingScreen(childId: childId, examId: exam.id, examTitle: exam.title),
                      )).then((_) => ref.invalidate(childOnlineExamsProvider(childId))),
            ),
          ],
        ),
      ),
    );
  }
}

/// Start → answer each question → submit → see the score and which answers
/// were right. One screen covers the whole flow; there's no back-and-forth
/// between separate "take" and "result" routes.
class ExamTakingScreen extends ConsumerStatefulWidget {
  const ExamTakingScreen({super.key, required this.childId, required this.examId, required this.examTitle});

  final String childId;
  final String examId;
  final String examTitle;

  @override
  ConsumerState<ExamTakingScreen> createState() => _ExamTakingScreenState();
}

class _ExamTakingScreenState extends ConsumerState<ExamTakingScreen> {
  ExamAttemptStart? _attempt;
  final Map<int, int?> _answers = {};
  bool _isSubmitting = false;
  String? _error;
  ExamSubmitResult? _result;

  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    try {
      final attempt = await ref.read(learningRepositoryProvider).startOnlineExam(widget.childId, widget.examId);
      setState(() {
        _attempt = attempt;
        for (final q in attempt.questions) {
          _answers[q.id] = null;
        }
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
  }

  Future<void> _submit() async {
    setState(() => _isSubmitting = true);
    try {
      final result = await ref.read(learningRepositoryProvider).submitOnlineExam(
        widget.childId,
        widget.examId,
        attemptId: _attempt!.attemptId,
        answers: _answers,
      );
      setState(() => _result = result);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.examTitle)),
      body: _body(context),
    );
  }

  Widget _body(BuildContext context) {
    if (_error != null) return _EmptyMessage(text: _error!);
    if (_result != null) return _ResultView(result: _result!, questions: _attempt!.questions);
    if (_attempt == null) return const Center(child: CircularProgressIndicator());

    final attempt = _attempt!;

    return Column(
      children: [
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.all(AppSpacing.md),
            itemCount: attempt.questions.length,
            itemBuilder: (context, i) => _QuestionCard(
              index: i,
              question: attempt.questions[i],
              selected: _answers[attempt.questions[i].id],
              onChanged: (choiceId) => setState(() => _answers[attempt.questions[i].id] = choiceId),
            ),
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: AppButton(label: 'Submit', variant: AppButtonVariant.gold, isLoading: _isSubmitting, onPressed: _submit),
          ),
        ),
      ],
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({required this.index, required this.question, required this.selected, required this.onChanged});

  final int index;
  final ExamQuestionToAnswer question;
  final int? selected;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${index + 1}. ${question.text}', style: AppTypography.bodyLg),
            RadioGroup<int>(
              groupValue: selected,
              onChanged: onChanged,
              child: Column(
                children: [
                  for (final choice in question.choices)
                    RadioListTile<int>(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(choice.text),
                      value: choice.id,
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ResultView extends StatelessWidget {
  const _ResultView({required this.result, required this.questions});

  final ExamSubmitResult result;
  final List<ExamQuestionToAnswer> questions;

  @override
  Widget build(BuildContext context) {
    final questionsById = {for (final q in questions) q.id: q};

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              children: [
                Text('${result.score} / ${result.maxScore}', style: AppTypography.displayLg.copyWith(color: AppColors.green700)),
                Text(context.tr('Your score'), style: AppTypography.bodySm),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        for (final answer in result.answers)
          Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                children: [
                  Icon(answer.isCorrect ? Icons.check_circle : Icons.cancel, color: answer.isCorrect ? AppColors.green700 : AppColors.error600),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: Text(questionsById[answer.questionId]?.text ?? '', style: AppTypography.bodyMd)),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _EmptyMessage extends StatelessWidget {
  const _EmptyMessage({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Text(text, textAlign: TextAlign.center, style: AppTypography.bodyMd),
      ),
    );
  }
}
