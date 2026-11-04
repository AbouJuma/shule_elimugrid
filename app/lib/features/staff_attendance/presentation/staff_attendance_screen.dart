import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/app_button.dart';
import '../../attendance/domain/attendance_models.dart';
import '../domain/staff_attendance_models.dart';
import 'staff_attendance_controller.dart';

class StaffAttendanceScreen extends ConsumerWidget {
  const StaffAttendanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncState = ref.watch(staffAttendanceControllerProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Staff Attendance'))),
      body: switch (asyncState) {
        AsyncData(:final value) => _Body(state: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load staff attendance.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.state});

  final StaffAttendanceState state;

  Future<void> _pickDate(BuildContext context, WidgetRef ref) async {
    final picked = await showDatePicker(context: context, initialDate: state.date, firstDate: DateTime(2020), lastDate: DateTime.now());
    if (picked != null) await ref.read(staffAttendanceControllerProvider.notifier).selectDate(picked);
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
              Expanded(child: Text(context.tr('Today\'s staff register'), style: AppTypography.bodyLg)),
              OutlinedButton.icon(
                onPressed: () => _pickDate(context, ref),
                icon: const Icon(Icons.calendar_today, size: 16),
                label: Text(state.dateString),
              ),
            ],
          ),
        ),
        if (register != null && register.locked) _LockedBanner(register: register),
        if (register == null)
          const Expanded(child: Center(child: CircularProgressIndicator()))
        else if (register.staff.isEmpty)
          Expanded(child: Center(child: Text(context.tr('No active staff on record yet.'))))
        else
          Expanded(
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              itemCount: register.staff.length,
              separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
              itemBuilder: (context, i) => _StaffRow(entry: register.staff[i], locked: register.locked),
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
                    child: Text(context.tr('Saved'), textAlign: TextAlign.center, style: AppTypography.bodySm.copyWith(color: AppColors.green700)),
                  ),
                if (register != null && register.locked)
                  (register.canUnlock
                      ? AppButton(
                          label: 'Unlock to Edit',
                          variant: AppButtonVariant.outline,
                          isLoading: state.isSaving,
                          onPressed: () async {
                            try {
                              await ref.read(staffAttendanceControllerProvider.notifier).unlock();
                            } on ApiException catch (e) {
                              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
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
                              await ref.read(staffAttendanceControllerProvider.notifier).save();
                            } on ApiException catch (e) {
                              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
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

  final StaffAttendanceRegister register;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      decoration: BoxDecoration(color: AppColors.warning100, borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
      child: Row(
        children: [
          const Icon(Icons.lock_outline, size: 18, color: AppColors.warning600),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              register.lockedBy != null ? context.tr('Locked by {name}', {'name': register.lockedBy!}) : context.tr('Locked'),
              style: AppTypography.bodySm.copyWith(color: AppColors.warning600),
            ),
          ),
        ],
      ),
    );
  }
}

class _StaffRow extends ConsumerWidget {
  const _StaffRow({required this.entry, required this.locked});

  final StaffAttendanceEntry entry;
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
                Text([?entry.employeeNumber, ?entry.jobTitle].join(' · '), style: AppTypography.bodySm),
              ],
            ),
          ),
          DropdownButton<AttendanceStatus>(
            value: entry.status,
            underline: const SizedBox.shrink(),
            onChanged: locked
                ? null
                : (value) {
                    if (value != null) ref.read(staffAttendanceControllerProvider.notifier).setStatus(entry.userId, value);
                  },
            items: AttendanceStatus.values
                .map((s) => DropdownMenuItem(value: s, child: Text(context.tr(s.label), style: TextStyle(color: _statusColor(s)))))
                .toList(),
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
