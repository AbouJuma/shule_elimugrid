import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/pdf_viewer_screen.dart';
import '../../timetable/presentation/timetable_controller.dart' show timetableClassesProvider;
import '../../attendance/domain/attendance_models.dart';
import '../domain/results_models.dart';
import 'results_controller.dart';

String _fmt(num? v) => v == null ? '—' : (v % 1 == 0 ? v.toInt().toString() : v.toStringAsFixed(1));

class ResultsScreen extends ConsumerStatefulWidget {
  const ResultsScreen({super.key});

  @override
  ConsumerState<ResultsScreen> createState() => _ResultsScreenState();
}

class _ResultsScreenState extends ConsumerState<ResultsScreen> {
  int? _termId;
  int? _classId;

  @override
  Widget build(BuildContext context) {
    final terms = ref.watch(resultTermsProvider);
    final classes = ref.watch(timetableClassesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Results'))),
      body: switch ((terms, classes)) {
        (AsyncData(value: final t), AsyncData(value: final c)) when t.isEmpty || c.isEmpty => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text(
              context.tr('Set up terms and classes on the portal first, then results will appear here.'),
              textAlign: TextAlign.center,
            ),
          ),
        ),
        (AsyncData(value: final t), AsyncData(value: final c)) => _content(context, t, c),
        (AsyncError(:final error), _) || (_, AsyncError(:final error)) => Center(child: Text('$error', textAlign: TextAlign.center)),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }

  Widget _content(BuildContext context, List<ResultTerm> terms, List<AttendanceClass> classes) {
    final termId = _termId ?? (terms.where((t) => t.isCurrent).firstOrNull ?? terms.first).id;
    final classId = _classId ?? classes.first.id;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int>(
                  initialValue: termId,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: context.tr('Term'), isDense: true),
                  items: terms.map((t) => DropdownMenuItem(value: t.id, child: Text(t.label, overflow: TextOverflow.ellipsis))).toList(),
                  onChanged: (v) => setState(() => _termId = v),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: DropdownButtonFormField<int>(
                  initialValue: classId,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: context.tr('Class'), isDense: true),
                  items: classes.map((c) => DropdownMenuItem<int>(value: c.id, child: Text(c.name))).toList(),
                  onChanged: (v) => setState(() => _classId = v),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: switch (ref.watch(classResultsProvider((termId: termId, classId: classId)))) {
            AsyncData(:final value) => _ClassResultsView(results: value),
            AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
            _ => const Center(child: CircularProgressIndicator()),
          },
        ),
      ],
    );
  }
}

class _ClassResultsView extends ConsumerWidget {
  const _ClassResultsView({required this.results});

  final ClassResults results;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (results.students.isEmpty) {
      return Center(child: Text(context.tr('No students in this class.')));
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.md, 0, AppSpacing.md, AppSpacing.lg),
      children: [
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(color: AppColors.navy100, borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
          child: Row(
            children: [
              _Stat(label: context.tr('Students'), value: '${results.classSize}'),
              _Stat(label: context.tr('Exams'), value: '${results.examsCount}'),
              _Stat(label: context.tr('Class average'), value: results.classAverage == null ? '—' : '${_fmt(results.classAverage)}%'),
            ],
          ),
        ),
        if (results.classPdfUrl.isNotEmpty)
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              icon: const Icon(Icons.picture_as_pdf_outlined, size: 18),
              label: Text(context.tr('Class report (PDF)')),
              onPressed: () => openPdf(
                context,
                title: 'Class report',
                loader: () => ref.read(resultsRepositoryProvider).downloadPdf(results.classPdfUrl),
              ),
            ),
          ),
        for (final s in results.students)
          Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: ListTile(
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => StudentResultScreen(result: s, exams: results.exams))),
              leading: CircleAvatar(
                backgroundColor: AppColors.gold100,
                child: Text(s.position?.toString() ?? '—', style: AppTypography.headingSm.copyWith(color: AppColors.gold700)),
              ),
              title: Text(s.name, style: AppTypography.bodyLg),
              subtitle: Text(
                '${context.tr('Average')} ${s.average == null ? '—' : '${_fmt(s.average)}%'} · ${s.admissionNumber}',
                style: AppTypography.bodySm,
              ),
              trailing: Text(s.grade ?? '—', style: AppTypography.headingMd),
            ),
          ),
      ],
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(children: [
      Text(value, style: AppTypography.headingMd),
      Text(label, style: AppTypography.bodySm, textAlign: TextAlign.center),
    ]),
  );
}

class StudentResultScreen extends ConsumerWidget {
  const StudentResultScreen({super.key, required this.result, this.exams = const []});

  final StudentResult result;
  final List<String> exams;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: Text(result.name)),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                children: [
                  _Stat(label: context.tr('Position'), value: result.position?.toString() ?? '—'),
                  _Stat(label: context.tr('Average'), value: result.average == null ? '—' : '${_fmt(result.average)}%'),
                  _Stat(label: context.tr('Grade'), value: result.grade ?? '—'),
                  if (result.attendanceRate != null) _Stat(label: context.tr('Attendance'), value: '${_fmt(result.attendanceRate)}%'),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Card(
            child: Column(
              children: [
                for (final l in result.lines)
                  ListTile(
                    dense: true,
                    title: Text(l.subject, style: AppTypography.bodyMd),
                    isThreeLine: exams.length >= 2 && l.examScores.length == exams.length,
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (exams.length >= 2 && l.examScores.length == exams.length)
                          Text(
                            [for (var i = 0; i < exams.length; i++) '${exams[i]} ${l.examScores[i] == null ? '—' : _fmt(l.examScores[i])}'].join(' · '),
                            style: AppTypography.bodySm,
                          ),
                        if (l.classAverage != null)
                          Text('${context.tr('Class average')} ${_fmt(l.classAverage)}%', style: AppTypography.bodySm),
                      ],
                    ),
                    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                      Text(l.percent == null ? '—' : '${_fmt(l.percent)}%', style: AppTypography.bodyMd),
                      const SizedBox(width: AppSpacing.md),
                      Text(l.grade ?? '—', style: AppTypography.headingSm),
                    ]),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          FilledButton.icon(
            icon: const Icon(Icons.picture_as_pdf_outlined),
            label: Text(context.tr('View report card (PDF)')),
            onPressed: () => openPdf(
              context,
              title: 'Report card',
              loader: () => ref.read(resultsRepositoryProvider).downloadPdf(result.pdfUrl),
            ),
          ),
        ],
      ),
    );
  }
}
