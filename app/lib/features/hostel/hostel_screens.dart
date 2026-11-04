import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_typography.dart';
import '../../shared/crud/crud_list.dart';
import '../../shared/forms/entity_form.dart';
import '../academics/academics_lookups.dart';
import '../auth/presentation/auth_controller.dart';

/// Hostels, their rooms, and which student sleeps where.
class HostelScreen extends ConsumerWidget {
  const HostelScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canManage = ref.watch(authControllerProvider).value?.user?.can('hostel.manage') ?? false;
    final api = ref.read(apiClientProvider);

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(title: Text(context.tr('Hostel')), bottom: TabBar(tabs: [Tab(text: context.tr('Hostels')), Tab(text: context.tr('Rooms')), Tab(text: context.tr('Allocations'))])),
        body: TabBarView(children: [
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No hostels yet.',
            addLabel: 'Add hostel',
            load: (_) => api.rows('/hostel/hostels'),
            onAdd: canManage ? (context) => openForm(context, const HostelFormScreen()) : null,
            tile: (context, h, reload) => ListTile(
              onTap: canManage
                  ? () => showActions(context, title: h['name'] as String, actions: [
                        SheetAction(Icons.meeting_room_outlined, 'Add a room', () async {
                          await openForm(context, RoomFormScreen(hostelId: h['id'] as int));
                          await reload();
                        }),
                        SheetAction(Icons.edit_outlined, 'Edit', () async {
                          if (await openForm(context, HostelFormScreen(hostel: h))) await reload();
                        }),
                        SheetAction(Icons.delete_outline, 'Delete', () async {
                          if (!await confirmAction(context, title: 'Delete this hostel?', message: h['name'] as String)) return;

                          try {
                            await api.delete('/hostel/hostels/${h['id']}');
                            await reload();
                          } catch (e) {
                            if (context.mounted) showError(context, e);
                          }
                        }, destructive: true),
                      ])
                  : null,
              leading: const CircleAvatar(backgroundColor: AppColors.navy100, child: Icon(Icons.apartment, color: AppColors.navy700)),
              title: Text(h['name'] as String, style: AppTypography.bodyLg),
              subtitle: Text(
                [
                  context.tr((h['gender'] as String)[0].toUpperCase() + (h['gender'] as String).substring(1)),
                  '${h['rooms']} ${context.tr('rooms')}',
                  if (h['warden'] != null) (h['warden'] as Map<String, dynamic>)['name'] as String,
                ].join(' · '),
                style: AppTypography.bodySm,
              ),
              trailing: StatusPill('${h['occupied']}/${h['capacity']}', tone: (h['capacity'] as int) > 0 && h['occupied'] == h['capacity'] ? PillTone.warn : PillTone.good),
            ),
          ),
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No rooms yet.',
            addLabel: 'Add room',
            load: (_) => api.rows('/hostel/rooms'),
            onAdd: canManage ? (context) => openForm(context, const RoomFormScreen()) : null,
            tile: (context, r, reload) {
              final full = r['occupied_count'] >= r['capacity'];

              return ListTile(
                onTap: canManage
                    ? () => showActions(context, title: '${r['hostel']} · ${r['room_number']}', subtitle: '${r['occupied_count']}/${r['capacity']}', actions: [
                          if (!full)
                            SheetAction(Icons.person_add_alt_1_outlined, 'Allocate a student', () async {
                              if (await openForm(context, AllocateScreen(room: r))) await reload();
                            }),
                          SheetAction(Icons.edit_outlined, 'Edit', () async {
                            if (await openForm(context, RoomFormScreen(room: r))) await reload();
                          }),
                          SheetAction(Icons.delete_outline, 'Delete', () async {
                            if (!await confirmAction(context, title: 'Delete this room?', message: '${r['room_number']}')) return;

                            try {
                              await api.delete('/hostel/rooms/${r['id']}');
                              await reload();
                            } catch (e) {
                              if (context.mounted) showError(context, e);
                            }
                          }, destructive: true),
                        ])
                    : null,
                leading: const CircleAvatar(backgroundColor: AppColors.gold100, child: Icon(Icons.bed_outlined, color: AppColors.gold700)),
                title: Text('${r['room_number']}', style: AppTypography.bodyLg),
                subtitle: Text('${r['hostel']}', style: AppTypography.bodySm),
                trailing: StatusPill('${r['occupied_count']}/${r['capacity']}', tone: full ? PillTone.warn : PillTone.good),
              );
            },
          ),
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No allocations yet.',
            load: (_) => api.rows('/hostel/allocations'),
            tile: (context, a, reload) {
              final active = a['status'] == 'active';

              return ListTile(
                onTap: canManage && active
                    ? () => showActions(context, title: '${a['student']}', subtitle: '${a['room']}', actions: [
                          SheetAction(Icons.logout, 'Vacate the room', () async {
                            try {
                              await api.patch('/hostel/allocations/${a['id']}/vacate');
                              await reload();
                            } catch (e) {
                              if (context.mounted) showError(context, e);
                            }
                          }, destructive: true),
                        ])
                    : null,
                title: Text('${a['student']}', style: AppTypography.bodyLg),
                subtitle: Text('${a['room']} · ${a['allocated_date']}', style: AppTypography.bodySm),
                trailing: StatusPill(context.tr(active ? 'Active' : 'Vacated'), tone: active ? PillTone.good : PillTone.neutral),
              );
            },
          ),
        ]),
      ),
    );
  }
}

class HostelFormScreen extends ConsumerWidget {
  const HostelFormScreen({super.key, this.hostel});

  final Map<String, dynamic>? hostel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final wardens = ref.watch(usersWithRoleProvider('Warden'));
    final h = hostel;

    if (!wardens.hasValue) return Scaffold(appBar: AppBar(title: Text(context.tr('Hostel'))), body: const Center(child: CircularProgressIndicator()));

    return EntityFormScreen(
      title: h == null ? 'Add hostel' : 'Edit hostel',
      fields: [
        FieldSpec(key: 'name', label: 'Hostel name', required: true, initial: h?['name'] as String?, maxLength: 150),
        FieldSpec(
          key: 'gender',
          label: 'Who stays here',
          kind: FieldKind.choice,
          required: true,
          initial: h?['gender'] as String?,
          choices: [FieldChoice('male', context.tr('Boys')), FieldChoice('female', context.tr('Girls')), FieldChoice('mixed', context.tr('Mixed'))],
        ),
        FieldSpec(
          key: 'warden_user_id',
          label: 'Warden',
          kind: FieldKind.choice,
          initial: (h?['warden'] as Map<String, dynamic>?)?['id'] as String?,
          choices: [for (final w in wardens.requireValue) FieldChoice(w.id, w.name)],
        ),
      ],
      onSubmit: (v) async {
        final data = {'name': v['name'], 'gender': v['gender'], 'warden_user_id': (v['warden_user_id'] ?? '').isEmpty ? null : v['warden_user_id']};
        final api = ref.read(apiClientProvider);

        if (h == null) {
          await api.post('/hostel/hostels', data: data);
        } else {
          await api.put('/hostel/hostels/${h['id']}', data: data);
        }
      },
    );
  }
}

class RoomFormScreen extends ConsumerWidget {
  const RoomFormScreen({super.key, this.room, this.hostelId});

  final Map<String, dynamic>? room;
  final int? hostelId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);
    final r = room;

    return FutureBuilder<List<Map<String, dynamic>>>(
      future: api.rows('/hostel/hostels'),
      builder: (context, snap) {
        if (!snap.hasData) return Scaffold(appBar: AppBar(title: Text(context.tr('Room'))), body: Center(child: snap.hasError ? Text('${snap.error}') : const CircularProgressIndicator()));

        return EntityFormScreen(
          title: r == null ? 'Add room' : 'Edit room',
          fields: [
            FieldSpec(
              key: 'hostel_id',
              label: 'Hostel',
              kind: FieldKind.choice,
              required: true,
              initial: (r?['hostel_id'] ?? hostelId)?.toString(),
              choices: [for (final h in snap.data!) FieldChoice('${h['id']}', h['name'] as String)],
            ),
            FieldSpec(key: 'room_number', label: 'Room number', required: true, initial: r?['room_number'] as String?, maxLength: 30),
            FieldSpec(key: 'capacity', label: 'Beds', kind: FieldKind.number, required: true, initial: r?['capacity']?.toString()),
          ],
          onSubmit: (v) async {
            final data = {'hostel_id': int.tryParse(v['hostel_id'] ?? ''), 'room_number': v['room_number'], 'capacity': int.tryParse(v['capacity'] ?? '')};

            if (r == null) {
              await api.post('/hostel/rooms', data: data);
            } else {
              await api.put('/hostel/rooms/${r['id']}', data: data);
            }
          },
        );
      },
    );
  }
}

class AllocateScreen extends ConsumerWidget {
  const AllocateScreen({super.key, required this.room});

  final Map<String, dynamic> room;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);

    return EntityFormScreen(
      title: 'Allocate a student',
      submitLabel: 'Allocate',
      fields: [FieldSpec(key: 'student_id', label: 'Student', kind: FieldKind.choice, required: true, search: (q) => searchStudents(api, q))],
      onSubmit: (v) async => api.post('/hostel/rooms/${room['id']}/allocate', data: {'student_id': v['student_id']}),
    );
  }
}
