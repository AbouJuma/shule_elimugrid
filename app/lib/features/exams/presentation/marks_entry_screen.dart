import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../domain/exam_models.dart';
import 'exams_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class MarksEntryScreen extends ConsumerStatefulWidget {
  const MarksEntryScreen({super.key, required this.examId, required this.subjectId, required this.classId});

  final int examId;
  final int subjectId;
  final int classId;

  @override
  ConsumerState<MarksEntryScreen> createState() => _MarksEntryScreenState();
}

class _MarksEntryScreenState extends ConsumerState<MarksEntryScreen> {
  final Map<int, TextEditingController> _controllers = {};
  bool _isSaving = false;
  String? _error;
  DateTime? _savedAt;

  TextEditingController _controllerFor(MarkEntry entry) {
    return _controllers.putIfAbsent(
      entry.studentId,
      () => TextEditingController(text: entry.marksObtained?.toString() ?? ''),
    );
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save(MarksRegister register) async {
    setState(() {
      _isSaving = true;
      _error = null;
    });

    final entries = register.students.map((s) {
      final text = _controllers[s.studentId]?.text.trim() ?? '';
      return s.copyWith(marksObtained: () => text.isEmpty ? null : num.tryParse(text));
    }).toList();

    try {
      await ref.read(examRepositoryProvider).recordMarks(
        examId: widget.examId,
        subjectId: widget.subjectId,
        classId: widget.classId,
        entries: entries,
      );
      setState(() => _savedAt = DateTime.now());
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final params = (examId: widget.examId, subjectId: widget.subjectId, classId: widget.classId);
    final asyncRegister = ref.watch(marksRegisterProvider(params));

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Record Marks'))),
      body: switch (asyncRegister) {
        AsyncData(:final value) => _buildBody(context, value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load the marks sheet.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }

  Widget _buildBody(BuildContext context, MarksRegister register) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(register.subjectName, style: AppTypography.headingSm),
              Text(
                '${register.examName} · ${register.className} · ${context.tr('Max marks: {n}', {'n': '${register.maxMarks}'})}',
                style: AppTypography.bodySm,
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            itemCount: register.students.length,
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, index) {
              final entry = register.students[index];
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(entry.name, style: AppTypography.bodyMd),
                          Text(entry.admissionNumber, style: AppTypography.bodySm),
                        ],
                      ),
                    ),
                    SizedBox(
                      width: 72,
                      child: TextField(
                        controller: _controllerFor(entry),
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        textAlign: TextAlign.center,
                        decoration: InputDecoration(
                          isDense: true,
                          hintText: '/ ${register.maxMarks}',
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Text(_error!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
                  ),
                if (_savedAt != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Text(
                      context.tr('Saved'),
                      textAlign: TextAlign.center,
                      style: AppTypography.bodySm.copyWith(color: AppColors.green700),
                    ),
                  ),
                AppButton(
                  label: 'Save Marks',
                  variant: AppButtonVariant.gold,
                  isLoading: _isSaving,
                  onPressed: () => _save(register),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
