import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_typography.dart';
import '../../shared/crud/crud_list.dart';
import '../../shared/forms/entity_form.dart';
import '../academics/academics_lookups.dart';
import '../auth/presentation/auth_controller.dart';

/// Staff records: onboard, edit, suspend. (Salaries live on the portal's payroll pages.)
class StaffListScreen extends ConsumerWidget {
  const StaffListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;
    final canManage = user?.can('hr.manage') ?? false;

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Staff'))),
      body: CrudListBody<Map<String, dynamic>>(
        searchHint: 'Search name…',
        emptyText: 'No staff found.',
        addLabel: 'Add staff',
        load: (q) => ref.read(apiClientProvider).rows('/staff', query: {'search': q, 'per_page': 100}),
        onAdd: canManage ? (context) => openForm(context, const StaffFormScreen()) : null,
        tile: (context, s, reload) {
          final profile = (s['profile'] as Map<String, dynamic>?) ?? const {};
          final active = s['status'] == 'active';

          return ListTile(
            onTap: canManage
                ? () async {
                    if (await openForm(context, StaffFormScreen(staff: s))) await reload();
                  }
                : null,
            leading: CircleAvatar(
              backgroundColor: AppColors.navy100,
              child: Text((s['name'] as String).isNotEmpty ? (s['name'] as String)[0].toUpperCase() : '?', style: AppTypography.headingSm.copyWith(color: AppColors.navy700)),
            ),
            title: Text(s['name'] as String, style: AppTypography.bodyLg),
            subtitle: Text(
              [
                if (profile['job_title'] != null) profile['job_title'] as String,
                if ((s['roles'] as List).isNotEmpty) (s['roles'] as List).join(', '),
                if (profile['employee_number'] != null) profile['employee_number'] as String,
              ].join(' · '),
              style: AppTypography.bodySm,
            ),
            trailing: StatusPill(context.tr(active ? 'Active' : 'Suspended'), tone: active ? PillTone.good : PillTone.bad),
          );
        },
      ),
    );
  }
}

/// Onboard a new staff member, or edit an existing one's details.
class StaffFormScreen extends ConsumerWidget {
  const StaffFormScreen({super.key, this.staff});

  final Map<String, dynamic>? staff;

  bool get _editing => staff != null;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final roles = ref.watch(roleNamesProvider);

    if (!roles.hasValue) {
      return Scaffold(appBar: AppBar(title: Text(context.tr('Staff'))), body: const Center(child: CircularProgressIndicator()));
    }

    final s = staff;
    final p = (s?['profile'] as Map<String, dynamic>?) ?? const {};
    String? str(Object? v) => v?.toString();

    // A staff member can't reassign their own role — same guard the
    // backend enforces (self-lockout prevention), mirrored here so the
    // field itself is visibly disabled rather than failing on submit.
    final isOwnRecord = _editing && s!['id'] == ref.read(authControllerProvider).value?.user?.id;
    final currentRole = _editing ? ((s!['roles'] as List).isNotEmpty ? (s['roles'] as List).first as String : null) : null;

    final employment = [
      FieldChoice('full_time', context.tr('Full time')),
      FieldChoice('part_time', context.tr('Part time')),
      FieldChoice('contract', context.tr('Contract')),
    ];

    return EntityFormScreen(
      title: _editing ? 'Edit staff' : 'Add staff',
      submitLabel: _editing ? 'Save' : 'Create account',
      actions: [
        if (_editing && (ref.read(authControllerProvider).value?.user?.can('users.suspend') ?? false))
          IconButton(
            tooltip: context.tr(s!['status'] == 'active' ? 'Suspend' : 'Reactivate'),
            icon: Icon(s['status'] == 'active' ? Icons.block : Icons.check_circle_outline),
            onPressed: () async {
              final suspend = s['status'] == 'active';
              if (!await confirmAction(context, title: suspend ? 'Suspend this account?' : 'Reactivate this account?', message: s['name'] as String, confirmLabel: suspend ? 'Suspend' : 'Reactivate')) return;

              try {
                await ref.read(apiClientProvider).patch('/staff/${s['id']}/status', data: {'status': suspend ? 'suspended' : 'active'});
                if (context.mounted) Navigator.of(context).pop(true);
              } catch (e) {
                if (context.mounted) showError(context, e);
              }
            },
          ),
      ],
      fields: [
        FieldSpec(key: 'name', label: 'Full name', required: true, initial: s?['name'] as String?, serverKey: 'user.name', section: 'Account'),
        FieldSpec(key: 'email', label: 'Email', kind: FieldKind.email, required: true, initial: s?['email'] as String?, serverKey: 'user.email'),
        FieldSpec(
          key: 'phone',
          label: 'Phone',
          kind: FieldKind.phone,
          // Only required when creating: it becomes their first login
          // password, so there has to be one to assign. Already-onboarded
          // staff may simply not have had one recorded yet.
          required: !_editing,
          hint: !_editing ? 'Their first login password — they can change it after signing in.' : null,
          initial: s?['phone'] as String?,
          serverKey: 'user.phone',
        ),
        FieldSpec(
          key: 'role',
          label: 'Role',
          kind: FieldKind.choice,
          required: true,
          initial: currentRole,
          hint: isOwnRecord ? 'You can\'t change your own role.' : null,
          choices: [for (final r in roles.requireValue) FieldChoice(r, r)],
          serverKey: 'user.role',
        ),
        FieldSpec(key: 'job_title', label: 'Job title', initial: str(p['job_title']), section: 'Employment', serverKey: 'profile.job_title'),
        FieldSpec(key: 'department', label: 'Department', initial: str(p['department']), serverKey: 'profile.department'),
        FieldSpec(key: 'employment_type', label: 'Employment type', kind: FieldKind.choice, required: true, choices: employment, initial: str(p['employment_type']) ?? 'full_time', serverKey: 'profile.employment_type'),
        FieldSpec(key: 'date_joined', label: 'Date joined', kind: FieldKind.date, required: true, initial: str(p['date_joined']) ?? _today(), firstDate: DateTime(1990), lastDate: DateTime.now().add(const Duration(days: 366)), serverKey: 'profile.date_joined'),
        if (_editing) FieldSpec(key: 'date_left', label: 'Date left (if they have left)', kind: FieldKind.date, initial: str(p['date_left']), firstDate: DateTime(1990), lastDate: DateTime.now().add(const Duration(days: 366))),
        FieldSpec(key: 'national_id', label: 'National ID', initial: str(p['national_id']), section: 'Personal', serverKey: 'profile.national_id'),
        FieldSpec(key: 'address', label: 'Address', kind: FieldKind.multiline, initial: str(p['address']), serverKey: 'profile.address'),
        FieldSpec(key: 'next_of_kin_name', label: 'Next of kin', initial: str(p['next_of_kin_name']), serverKey: 'profile.next_of_kin_name'),
        FieldSpec(key: 'next_of_kin_phone', label: 'Next of kin phone', kind: FieldKind.phone, initial: str(p['next_of_kin_phone']), serverKey: 'profile.next_of_kin_phone'),
      ],
      onSubmit: (v) async {
        String? blank(String k) => (v[k] ?? '').isEmpty ? null : v[k];
        final api = ref.read(apiClientProvider);

        final profile = {
          'job_title': blank('job_title'),
          'department': blank('department'),
          'employment_type': v['employment_type'],
          'date_joined': v['date_joined'],
          'national_id': blank('national_id'),
          'address': blank('address'),
          'next_of_kin_name': blank('next_of_kin_name'),
          'next_of_kin_phone': blank('next_of_kin_phone'),
        };

        if (_editing) {
          await api.put('/staff/${s!['id']}', data: {
            ...profile,
            'date_left': blank('date_left'),
            'name': v['name'],
            'email': v['email'],
            'phone': blank('phone'),
            // The server enforces this too (defense in depth) — skipped
            // here as well so the request doesn't even try.
            if (!isOwnRecord) 'role': v['role'],
          });
          return;
        }

        final result = await api.post('/staff', data: {
          'user': {'name': v['name'], 'email': v['email'], 'phone': blank('phone'), 'role': v['role']},
          'profile': profile,
        });

        // The temporary password is only ever shown here, once.
        if (context.mounted) {
          await showDialog<void>(
            context: context,
            barrierDismissible: false,
            builder: (context) => AlertDialog(
              title: Text(context.tr('Account created')),
              content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(context.tr('{name}\'s first password is their phone number, shown below. They can change it after signing in.', {'name': v['name']!})),
                const SizedBox(height: AppSpacing.md),
                SelectableText(result['temporary_password'] as String, style: AppTypography.headingMd),
              ]),
              actions: [FilledButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Done')))],
            ),
          );
        }
      },
    );
  }

  static String _today() {
    final n = DateTime.now();

    return '${n.year.toString().padLeft(4, '0')}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
  }
}
