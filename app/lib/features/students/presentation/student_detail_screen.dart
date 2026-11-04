import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/student_model.dart';
import '../../auth/presentation/auth_controller.dart';
import 'student_detail_controller.dart';
import 'student_form_screen.dart';
import 'student_login_actions.dart';
import 'students_list_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class StudentDetailScreen extends ConsumerWidget {
  const StudentDetailScreen({super.key, required this.studentId});

  final String studentId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncStudent = ref.watch(studentDetailProvider(studentId));

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Student')),
        actions: [
          if ((ref.watch(authControllerProvider).value?.user?.can('students.update') ?? false) && asyncStudent.hasValue)
            IconButton(
              tooltip: context.tr('Edit'),
              icon: const Icon(Icons.edit_outlined),
              onPressed: () async {
                final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => StudentFormScreen(student: asyncStudent.requireValue)));
                if (saved == true) {
                  ref.invalidate(studentDetailProvider(studentId));
                  ref.invalidate(studentsListControllerProvider);
                }
              },
            ),
        ],
      ),
      body: switch (asyncStudent) {
        AsyncData(:final value) => _StudentDetailBody(student: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load this student.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _StudentDetailBody extends ConsumerWidget {
  const _StudentDetailBody({required this.student});

  final StudentModel student;

  Future<void> _login(BuildContext context, WidgetRef ref) async {
    final changed = await manageStudentLogin(context, ref, student);
    if (changed) ref.invalidate(studentDetailProvider(student.id));
  }

  Future<void> _changeStatus(BuildContext context, WidgetRef ref) async {
    final result = await showDialog<(String, String)>(
      context: context,
      builder: (_) => _StatusDialog(current: student.status),
    );
    if (result == null) return;

    try {
      await ref.read(studentRepositoryProvider).changeStatus(student.id, status: result.$1, reason: result.$2);
      ref.invalidate(studentDetailProvider(student.id));
      ref.invalidate(studentsListControllerProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.tr('Status updated.'))));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e is ApiException ? e.message : '$e')));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canChangeStatus = ref.watch(authControllerProvider).value?.user?.can('students.archive') ?? false;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Center(
          child: CircleAvatar(
            radius: 40,
            backgroundColor: AppColors.navy100,
            backgroundImage: student.photoUrl != null ? NetworkImage(student.photoUrl!) : null,
            child: student.photoUrl == null
                ? Text(
                    student.firstName.isNotEmpty ? student.firstName[0] : '?',
                    style: AppTypography.displayLg.copyWith(color: AppColors.navy700),
                  )
                : null,
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Center(child: Text(student.fullName, style: AppTypography.headingLg)),
        Center(
          child: Text(
            student.admissionNumber,
            style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        _Section(
          title: 'Academic',
          rows: [
            ('Class', student.schoolClass?.name ?? '—'),
            ('Stream', student.stream?.name ?? '—'),
            ('Status', context.tr(student.status[0].toUpperCase() + student.status.substring(1))),
            if (student.controlNumber != null) ('Fee control number', student.controlNumber!),
          ],
        ),
        if (canChangeStatus)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              icon: const Icon(Icons.swap_horiz, size: 18),
              label: Text(context.tr('Change status')),
              onPressed: () => _changeStatus(context, ref),
            ),
          ),
        if (ref.watch(authControllerProvider).value?.user?.can('students.update') ?? false)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              icon: Icon(student.hasLogin ? Icons.lock_reset : Icons.key_outlined, size: 18),
              label: Text(context.tr(student.hasLogin ? 'Reset password' : 'Create student login')),
              onPressed: () => _login(context, ref),
            ),
          ),
        const SizedBox(height: AppSpacing.md),
        _Section(
          title: 'Personal',
          rows: [
            ('Gender', context.tr(student.gender[0].toUpperCase() + student.gender.substring(1))),
            ('Date of birth', student.dateOfBirth ?? '—'),
            ('Admission date', student.admissionDate ?? '—'),
          ],
        ),
        if (student.guardians.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          Text(context.tr('Guardians'), style: AppTypography.headingSm),
          const SizedBox(height: AppSpacing.sm),
          ...student.guardians.map(
            (g) => Card(
              margin: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: ListTile(
                title: Text(g.name),
                subtitle: Text('${context.tr(g.relationship[0].toUpperCase() + g.relationship.substring(1))} · ${g.phone}'),
                trailing: g.isPrimary
                    ? const Icon(Icons.star, color: AppColors.gold600, size: 20)
                    : null,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.rows});

  final String title;
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(context.tr(title), style: AppTypography.headingSm),
            const SizedBox(height: AppSpacing.sm),
            ...rows.map(
              (r) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(context.tr(r.$1), style: AppTypography.bodySm),
                    Text(context.tr(r.$2), style: AppTypography.bodyMd),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusDialog extends StatefulWidget {
  const _StatusDialog({required this.current});

  final String current;

  @override
  State<_StatusDialog> createState() => _StatusDialogState();
}

class _StatusDialogState extends State<_StatusDialog> {
  static const _statuses = ['active', 'suspended', 'graduated', 'withdrawn', 'transferred'];

  late String _status = widget.current;
  final _reason = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.tr('Change status')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _status,
            decoration: InputDecoration(labelText: context.tr('New status')),
            items: _statuses
                .map((s) => DropdownMenuItem(value: s, child: Text(context.tr(s[0].toUpperCase() + s.substring(1)))))
                .toList(),
            onChanged: (v) => setState(() => _status = v ?? _status),
          ),
          const SizedBox(height: AppSpacing.sm),
          TextField(
            controller: _reason,
            maxLines: 3,
            decoration: InputDecoration(labelText: context.tr('Reason (required)'), errorText: _error),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Cancel'))),
        FilledButton(
          onPressed: () {
            if (_status == widget.current) {
              setState(() => _error = context.tr('Pick a different status.'));
            } else if (_reason.text.trim().length < 3) {
              setState(() => _error = context.tr('Give a reason (at least 3 characters).'));
            } else {
              Navigator.pop(context, (_status, _reason.text.trim()));
            }
          },
          child: Text(context.tr('Save')),
        ),
      ],
    );
  }
}
