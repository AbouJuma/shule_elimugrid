import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_typography.dart';
import '../../shared/crud/crud_list.dart';
import '../../shared/forms/entity_form.dart';
import '../auth/presentation/auth_controller.dart';
import 'academics_lookups.dart';

/// Set-up: classes (with their streams), subjects and terms.
class AcademicsScreen extends ConsumerWidget {
  const AcademicsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;
    final canClasses = user?.can('classes.manage') ?? false;
    final canSubjects = user?.can('subjects.manage') ?? false;
    final canTerms = user?.can('academic_years.manage') ?? false;
    final api = ref.read(apiClientProvider);

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(title: Text(context.tr('Academics')), bottom: TabBar(tabs: [Tab(text: context.tr('Classes')), Tab(text: context.tr('Subjects')), Tab(text: context.tr('Terms'))])),
        body: TabBarView(children: [
          // ---- Classes and their streams ----
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No classes yet.',
            addLabel: 'Add class',
            load: (_) => api.rows('/classes'),
            onAdd: canClasses ? (context) => openForm(context, const ClassFormScreen()) : null,
            tile: (context, c, reload) {
              final streams = (c['streams'] as List<dynamic>).cast<Map<String, dynamic>>();

              return ExpansionTile(
                leading: const CircleAvatar(backgroundColor: AppColors.navy100, child: Icon(Icons.class_outlined, color: AppColors.navy700)),
                title: Text(c['name'] as String, style: AppTypography.bodyLg),
                subtitle: Text('${streams.length} ${context.tr('streams')}', style: AppTypography.bodySm),
                children: [
                  for (final s in streams)
                    ListTile(
                      dense: true,
                      contentPadding: const EdgeInsets.only(left: 72, right: 16),
                      title: Text(s['name'] as String),
                      subtitle: s['capacity'] != null ? Text('${context.tr('Capacity')} ${s['capacity']}') : null,
                      trailing: canClasses
                          ? Row(mainAxisSize: MainAxisSize.min, children: [
                              IconButton(
                                icon: const Icon(Icons.edit_outlined, size: 18),
                                onPressed: () async {
                                  if (await openForm(context, StreamFormScreen(classId: c['id'] as int, stream: s))) await reload();
                                },
                              ),
                              IconButton(
                                icon: const Icon(Icons.delete_outline, size: 18, color: AppColors.error600),
                                onPressed: () async {
                                  if (!await confirmAction(context, title: 'Delete this stream?', message: s['name'] as String)) return;

                                  try {
                                    await api.delete('/streams/${s['id']}');
                                    await reload();
                                  } catch (e) {
                                    if (context.mounted) showError(context, e);
                                  }
                                },
                              ),
                            ])
                          : null,
                    ),
                  if (canClasses)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(72, 0, 16, AppSpacing.sm),
                      child: Wrap(spacing: AppSpacing.sm, children: [
                        OutlinedButton.icon(
                          icon: const Icon(Icons.add, size: 18),
                          label: Text(context.tr('Add stream')),
                          onPressed: () async {
                            if (await openForm(context, StreamFormScreen(classId: c['id'] as int))) await reload();
                          },
                        ),
                        OutlinedButton.icon(
                          icon: const Icon(Icons.edit_outlined, size: 18),
                          label: Text(context.tr('Edit class')),
                          onPressed: () async {
                            if (await openForm(context, ClassFormScreen(cls: c))) await reload();
                          },
                        ),
                        OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(foregroundColor: AppColors.error600),
                          icon: const Icon(Icons.delete_outline, size: 18),
                          label: Text(context.tr('Delete')),
                          onPressed: () async {
                            if (!await confirmAction(context, title: 'Delete this class?', message: c['name'] as String)) return;

                            try {
                              await api.delete('/classes/${c['id']}');
                              await reload();
                            } catch (e) {
                              if (context.mounted) showError(context, e);
                            }
                          },
                        ),
                      ]),
                    ),
                ],
              );
            },
          ),
          // ---- Subjects ----
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No subjects yet.',
            addLabel: 'Add subject',
            load: (_) => api.rows('/subjects'),
            onAdd: canSubjects ? (context) => openForm(context, const SubjectFormScreen()) : null,
            tile: (context, s, reload) => ListTile(
              onTap: canSubjects
                  ? () => showActions(context, title: s['name'] as String, actions: [
                        SheetAction(Icons.edit_outlined, 'Edit', () async {
                          if (await openForm(context, SubjectFormScreen(subject: s))) await reload();
                        }),
                        SheetAction(Icons.delete_outline, 'Delete', () async {
                          if (!await confirmAction(context, title: 'Delete this subject?', message: s['name'] as String)) return;

                          try {
                            await api.delete('/subjects/${s['id']}');
                            await reload();
                          } catch (e) {
                            if (context.mounted) showError(context, e);
                          }
                        }, destructive: true),
                      ])
                  : null,
              leading: const CircleAvatar(backgroundColor: AppColors.gold100, child: Icon(Icons.menu_book_outlined, color: AppColors.gold700)),
              title: Text(s['name'] as String, style: AppTypography.bodyLg),
              subtitle: Text([if (s['code'] != null) s['code'], ((s['classes'] as List?) ?? const []).join(', ')].where((e) => '$e'.isNotEmpty).join(' · '), style: AppTypography.bodySm),
            ),
          ),
          // ---- Terms ----
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No terms yet.',
            addLabel: 'Add term',
            load: (_) => api.rows('/terms'),
            onAdd: canTerms ? (context) => openForm(context, const TermFormScreen()) : null,
            tile: (context, t, reload) => ListTile(
              onTap: canTerms
                  ? () => showActions(context, title: t['name'] as String, subtitle: t['year'] as String?, actions: [
                        SheetAction(Icons.edit_outlined, 'Edit', () async {
                          if (await openForm(context, TermFormScreen(term: t))) await reload();
                        }),
                        SheetAction(Icons.delete_outline, 'Delete', () async {
                          if (!await confirmAction(context, title: 'Delete this term?', message: t['name'] as String)) return;

                          try {
                            await api.delete('/terms/${t['id']}');
                            await reload();
                          } catch (e) {
                            if (context.mounted) showError(context, e);
                          }
                        }, destructive: true),
                      ])
                  : null,
              leading: const CircleAvatar(backgroundColor: AppColors.green100, child: Icon(Icons.event_note_outlined, color: AppColors.green700)),
              title: Text('${t['name']} · ${t['year']}', style: AppTypography.bodyLg),
              subtitle: Text('${t['start_date']} → ${t['end_date']}', style: AppTypography.bodySm),
              trailing: t['is_current'] == true ? StatusPill(context.tr('Current'), tone: PillTone.good) : null,
            ),
          ),
        ]),
      ),
    );
  }
}

class ClassFormScreen extends ConsumerWidget {
  const ClassFormScreen({super.key, this.cls});

  final Map<String, dynamic>? cls;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = cls;

    return EntityFormScreen(
      title: c == null ? 'Add class' : 'Edit class',
      fields: [
        FieldSpec(key: 'name', label: 'Class name', required: true, initial: c?['name'] as String?, maxLength: 100, hint: 'e.g. Form 1'),
        FieldSpec(key: 'order', label: 'Order in the school (1 = first)', kind: FieldKind.number, initial: c?['order']?.toString()),
      ],
      onSubmit: (v) async {
        final data = {'name': v['name'], if ((v['order'] ?? '').isNotEmpty) 'order': int.tryParse(v['order']!)};
        final api = ref.read(apiClientProvider);

        if (c == null) {
          await api.post('/classes', data: data);
        } else {
          await api.put('/classes/${c['id']}', data: data);
        }
      },
    );
  }
}

class StreamFormScreen extends ConsumerWidget {
  const StreamFormScreen({super.key, required this.classId, this.stream});

  final int classId;
  final Map<String, dynamic>? stream;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = stream;

    return EntityFormScreen(
      title: s == null ? 'Add stream' : 'Edit stream',
      fields: [
        FieldSpec(key: 'name', label: 'Stream name', required: true, initial: s?['name'] as String?, maxLength: 100, hint: 'e.g. Blue'),
        FieldSpec(key: 'capacity', label: 'Capacity', kind: FieldKind.number, initial: s?['capacity']?.toString()),
      ],
      onSubmit: (v) async {
        final data = {'school_class_id': classId, 'name': v['name'], if ((v['capacity'] ?? '').isNotEmpty) 'capacity': int.tryParse(v['capacity']!)};
        final api = ref.read(apiClientProvider);

        if (s == null) {
          await api.post('/streams', data: data);
        } else {
          await api.put('/streams/${s['id']}', data: data);
        }
      },
    );
  }
}

class SubjectFormScreen extends ConsumerWidget {
  const SubjectFormScreen({super.key, this.subject});

  final Map<String, dynamic>? subject;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final classes = ref.watch(classOptionsProvider);
    final s = subject;

    if (!classes.hasValue) return Scaffold(appBar: AppBar(title: Text(context.tr('Subject'))), body: const Center(child: CircularProgressIndicator()));

    return EntityFormScreen(
      title: s == null ? 'Add subject' : 'Edit subject',
      fields: [
        FieldSpec(key: 'name', label: 'Subject name', required: true, initial: s?['name'] as String?, maxLength: 100),
        FieldSpec(key: 'code', label: 'Code', initial: s?['code'] as String?, maxLength: 20),
        FieldSpec(
          key: 'school_class_ids',
          label: 'Taught in these classes',
          kind: FieldKind.multi,
          initial: ((s?['class_ids'] as List<dynamic>?) ?? const []).join(','),
          choices: [for (final c in classes.requireValue) FieldChoice('${c.id}', c.name)],
        ),
      ],
      onSubmit: (v) async {
        final data = {
          'name': v['name'],
          'code': (v['code'] ?? '').isEmpty ? null : v['code'],
          'school_class_ids': [for (final id in (v['school_class_ids'] ?? '').split(',')) if (id.isNotEmpty) int.parse(id)],
        };
        final api = ref.read(apiClientProvider);

        if (s == null) {
          await api.post('/subjects', data: data);
        } else {
          await api.put('/subjects/${s['id']}', data: data);
        }
        ref.invalidate(subjectOptionsProvider);
      },
    );
  }
}

class TermFormScreen extends ConsumerWidget {
  const TermFormScreen({super.key, this.term});

  final Map<String, dynamic>? term;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final years = ref.watch(yearOptionsProvider);
    final t = term;

    if (!years.hasValue) return Scaffold(appBar: AppBar(title: Text(context.tr('Term'))), body: Center(child: years.hasError ? Text('${years.error}') : const CircularProgressIndicator()));

    final current = years.requireValue.where((y) => y.isCurrent).firstOrNull ?? years.requireValue.firstOrNull;

    return EntityFormScreen(
      title: t == null ? 'Add term' : 'Edit term',
      fields: [
        if (t == null)
          FieldSpec(
            key: 'academic_year_id',
            label: 'Academic year',
            kind: FieldKind.choice,
            required: true,
            initial: current != null ? '${current.id}' : null,
            choices: [for (final y in years.requireValue) FieldChoice('${y.id}', y.name)],
          ),
        FieldSpec(key: 'name', label: 'Term name', required: true, initial: t?['name'] as String?, maxLength: 100, hint: 'e.g. Term 1'),
        FieldSpec(key: 'start_date', label: 'Starts', kind: FieldKind.date, required: true, initial: t?['start_date'] as String?, firstDate: DateTime(2020), lastDate: DateTime(DateTime.now().year + 3)),
        FieldSpec(key: 'end_date', label: 'Ends', kind: FieldKind.date, required: true, initial: t?['end_date'] as String?, firstDate: DateTime(2020), lastDate: DateTime(DateTime.now().year + 3)),
        FieldSpec(
          key: 'is_current',
          label: 'Current term?',
          kind: FieldKind.choice,
          initial: (t?['is_current'] == true) ? '1' : '0',
          choices: [FieldChoice('0', context.tr('No')), FieldChoice('1', context.tr('Yes — this is the current term'))],
        ),
      ],
      onSubmit: (v) async {
        final data = {
          if (t == null) 'academic_year_id': int.tryParse(v['academic_year_id'] ?? ''),
          'name': v['name'],
          'start_date': v['start_date'],
          'end_date': v['end_date'],
          'is_current': v['is_current'] == '1',
        };
        final api = ref.read(apiClientProvider);

        if (t == null) {
          await api.post('/terms', data: data);
        } else {
          await api.put('/terms/${t['id']}', data: data);
        }
      },
    );
  }
}
