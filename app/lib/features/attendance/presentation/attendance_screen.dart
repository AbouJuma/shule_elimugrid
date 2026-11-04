import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../domain/attendance_models.dart';
import 'attendance_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class AttendanceScreen extends ConsumerWidget {
  const AttendanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncState = ref.watch(attendanceControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Attendance'))),
      body: switch (asyncState) {
        AsyncData(:final value) when value.classes.isEmpty => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text(context.tr('No classes have been set up yet.'), textAlign: TextAlign.center),
          ),
        ),
        AsyncData(:final value) => _AttendanceBody(state: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load attendance.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _AttendanceBody extends ConsumerWidget {
  const _AttendanceBody({required this.state});

  final AttendanceState state;

  Future<void> _pickDate(BuildContext context, WidgetRef ref) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: state.date,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      await ref.read(attendanceControllerProvider.notifier).selectDate(picked);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final register = state.register;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int>(
                  initialValue: state.selectedClassId,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: context.tr('Class'), isDense: true),
                  items: state.classes
                      .map((c) => DropdownMenuItem(value: c.id, child: Text(c.name)))
                      .toList(),
                  onChanged: (value) {
                    if (value != null) ref.read(attendanceControllerProvider.notifier).selectClass(value);
                  },
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              OutlinedButton.icon(
                onPressed: () => _pickDate(context, ref),
                icon: const Icon(Icons.calendar_today, size: 16),
                label: Text(state.dateString),
              ),
            ],
          ),
        ),
        _SubjectTabs(state: state),
        if (register != null && register.locked) _LockedBanner(register: register),
        if (register == null)
          const Expanded(child: Center(child: CircularProgressIndicator()))
        else
          Expanded(
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              itemCount: register.students.length,
              separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
              itemBuilder: (context, index) => _StudentRow(entry: register.students[index], locked: register.locked),
            ),
          ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (state.savedAt != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Text(
                      context.tr('Saved'),
                      textAlign: TextAlign.center,
                      style: AppTypography.bodySm.copyWith(color: AppColors.green700),
                    ),
                  ),
                if (register != null && register.locked)
                  (register.canUnlock
                      ? AppButton(
                          label: 'Unlock to Edit',
                          variant: AppButtonVariant.outline,
                          isLoading: state.isSaving,
                          onPressed: () async {
                            try {
                              await ref.read(attendanceControllerProvider.notifier).unlock();
                            } on ApiException catch (e) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
                              }
                            }
                          },
                        )
                      : Text(
                          context.tr('This register is locked. Ask an administrator to unlock it to make changes.'),
                          textAlign: TextAlign.center,
                          style: AppTypography.bodySm.copyWith(color: AppColors.textSecondary),
                        ))
                else
                  AppButton(
                    label: 'Save Attendance',
                    variant: AppButtonVariant.gold,
                    isLoading: state.isSaving,
                    onPressed: register == null
                        ? null
                        : () async {
                            try {
                              await ref.read(attendanceControllerProvider.notifier).save();
                            } on ApiException catch (e) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
                              }
                            }
                          },
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _LockedBanner extends StatelessWidget {
  const _LockedBanner({required this.register});

  final AttendanceRegister register;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      decoration: BoxDecoration(
        color: AppColors.warning100,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
      ),
      child: Row(
        children: [
          const Icon(Icons.lock_outline, size: 18, color: AppColors.warning600),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              register.lockedBy != null
                  ? context.tr('Locked by {name}', {'name': register.lockedBy!})
                  : context.tr('Locked'),
              style: AppTypography.bodySm.copyWith(color: AppColors.warning600),
            ),
          ),
        ],
      ),
    );
  }
}

/// "By Class" (the whole-day register, subjectId null) plus one tab per
/// subject the selected class offers — lets a teacher switch between
/// marking the homeroom register and a specific lesson's register for
/// the same date without leaving the screen.
class _SubjectTabs extends ConsumerWidget {
  const _SubjectTabs({required this.state});

  final AttendanceState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final subjects = state.subjectsForSelectedClass;
    if (subjects.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: 40,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        children: [
          Padding(
            padding: const EdgeInsets.only(right: AppSpacing.sm),
            child: ChoiceChip(
              label: Text(context.tr('By Class')),
              selected: state.selectedSubjectId == null,
              onSelected: (_) => ref.read(attendanceControllerProvider.notifier).selectSubject(null),
            ),
          ),
          for (final subject in subjects)
            Padding(
              padding: const EdgeInsets.only(right: AppSpacing.sm),
              child: ChoiceChip(
                label: Text(subject.name),
                selected: state.selectedSubjectId == subject.id,
                onSelected: (_) => ref.read(attendanceControllerProvider.notifier).selectSubject(subject.id),
              ),
            ),
        ],
      ),
    );
  }
}

class _StudentRow extends ConsumerWidget {
  const _StudentRow({required this.entry, required this.locked});

  final RegisterEntry entry;
  final bool locked;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
          DropdownButton<AttendanceStatus>(
            value: entry.status,
            underline: const SizedBox.shrink(),
            items: AttendanceStatus.values
                .map((s) => DropdownMenuItem(
                      value: s,
                      child: Text(context.tr(s.label), style: TextStyle(color: _statusColor(s))),
                    ))
                .toList(),
            onChanged: locked
                ? null
                : (value) {
                    if (value != null) {
                      ref.read(attendanceControllerProvider.notifier).setStatus(entry.studentId, value);
                    }
                  },
          ),
        ],
      ),
    );
  }

  Color _statusColor(AttendanceStatus status) => switch (status) {
    AttendanceStatus.present => AppColors.green700,
    AttendanceStatus.absent => AppColors.error600,
    AttendanceStatus.late => AppColors.warning600,
    AttendanceStatus.excused => AppColors.info600,
    AttendanceStatus.unmarked => AppColors.textSecondary,
  };
}
