import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../shared/forms/entity_form.dart';
import '../../academics/academics_lookups.dart';
import '../../guardians/domain/guardian_model.dart';
import '../domain/student_model.dart';

String _today() {
  final n = DateTime.now();

  return '${n.year.toString().padLeft(4, '0')}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
}

/// Admit a new student (with a guardian) or edit an existing student's details.
/// Status changes stay on the student's own "Change status" action, which
/// demands a reason.
class StudentFormScreen extends ConsumerWidget {
  const StudentFormScreen({super.key, this.student});

  final StudentModel? student;

  bool get _editing => student != null;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final years = ref.watch(yearOptionsProvider);
    final classes = ref.watch(classOptionsProvider);
    final guardians = _editing ? null : ref.watch(guardianOptionsProvider);

    if (years.hasError || classes.hasError) {
      return Scaffold(
        appBar: AppBar(title: Text(context.tr('Student'))),
        body: Center(child: Text('${years.error ?? classes.error}', textAlign: TextAlign.center)),
      );
    }

    if (!years.hasValue || !classes.hasValue || (guardians != null && !guardians.hasValue)) {
      return Scaffold(appBar: AppBar(title: Text(context.tr('Student'))), body: const Center(child: CircularProgressIndicator()));
    }

    final yearList = years.requireValue;
    final classList = classes.requireValue;
    final existing = guardians?.value ?? const <GuardianModel>[];

    final genders = [FieldChoice('male', context.tr('Male')), FieldChoice('female', context.tr('Female'))];
    final relationships = [
      FieldChoice('father', context.tr('Father')),
      FieldChoice('mother', context.tr('Mother')),
      FieldChoice('guardian', context.tr('Guardian')),
      FieldChoice('other', context.tr('Other')),
    ];

    final classChoices = [for (final c in classList) FieldChoice('${c.id}', c.name)];
    List<FieldChoice> streamChoices(Map<String, String> v) {
      final cls = classList.where((c) => '${c.id}' == v['school_class_id']).firstOrNull;

      return [for (final s in cls?.streams ?? const <StreamOption>[]) FieldChoice('${s.id}', s.name)];
    }

    final current = yearList.where((y) => y.isCurrent).firstOrNull ?? yearList.firstOrNull;
    final s = student;

    final fields = <FieldSpec>[
      FieldSpec(key: 'first_name', label: 'First name', required: true, initial: s?.firstName, maxLength: 255, section: 'Student', serverKey: 'student.first_name'),
      FieldSpec(key: 'last_name', label: 'Last name', required: true, initial: s?.lastName, maxLength: 255, serverKey: 'student.last_name'),
      FieldSpec(key: 'gender', label: 'Gender', kind: FieldKind.choice, required: true, choices: genders, initial: s?.gender, serverKey: 'student.gender'),
      FieldSpec(
        key: 'date_of_birth',
        label: 'Date of birth',
        kind: FieldKind.date,
        required: true,
        initial: s?.dateOfBirth,
        hint: 'Choose a date',
        pickerStart: DateTime(DateTime.now().year - 10, 1, 1),
        lastDate: DateTime.now().subtract(const Duration(days: 1)),
        serverKey: 'student.date_of_birth',
      ),
      if (!_editing) ...[
        FieldSpec(key: 'admission_date', label: 'Admission date', kind: FieldKind.date, required: true, initial: _today(), lastDate: DateTime.now().add(const Duration(days: 366)), serverKey: 'student.admission_date'),
        FieldSpec(
          key: 'academic_year_id',
          label: 'Academic year',
          kind: FieldKind.choice,
          required: true,
          choices: [for (final y in yearList) FieldChoice('${y.id}', y.name)],
          initial: current != null ? '${current.id}' : null,
          serverKey: 'student.academic_year_id',
        ),
      ],
      FieldSpec(key: 'school_class_id', label: 'Class', kind: FieldKind.choice, choices: classChoices, initial: s?.schoolClass != null ? '${s!.schoolClass!.id}' : null, serverKey: 'student.school_class_id'),
      FieldSpec(
        key: 'stream_id',
        label: 'Stream',
        kind: FieldKind.choice,
        choicesFor: streamChoices,
        visibleWhen: (v) => streamChoices(v).isNotEmpty,
        initial: s?.stream != null ? '${s!.stream!.id}' : null,
        serverKey: 'student.stream_id',
      ),
      if (!_editing) ...[
        FieldSpec(
          key: 'guardian_id',
          label: 'Guardian',
          kind: FieldKind.choice,
          section: 'Guardian',
          choices: [FieldChoice('new', context.tr('New guardian…')), for (final g in existing) FieldChoice('${g.id}', '${g.name} · ${g.phone}')],
          initial: 'new',
          required: true,
        ),
        FieldSpec(key: 'guardian_name', label: 'Guardian name', required: true, visibleWhen: (v) => v['guardian_id'] == 'new', serverKey: 'guardians.0.name'),
        FieldSpec(key: 'guardian_phone', label: 'Guardian phone', kind: FieldKind.phone, required: true, visibleWhen: (v) => v['guardian_id'] == 'new', serverKey: 'guardians.0.phone'),
        FieldSpec(key: 'guardian_email', label: 'Guardian email', kind: FieldKind.email, visibleWhen: (v) => v['guardian_id'] == 'new', serverKey: 'guardians.0.email'),
        FieldSpec(key: 'guardian_occupation', label: 'Occupation', visibleWhen: (v) => v['guardian_id'] == 'new', serverKey: 'guardians.0.occupation'),
        FieldSpec(key: 'relationship', label: 'Relationship', kind: FieldKind.choice, required: true, choices: relationships, initial: 'guardian', serverKey: 'guardians.0.relationship'),
      ],
    ];

    return EntityFormScreen(
      title: _editing ? 'Edit student' : 'Admit a student',
      submitLabel: _editing ? 'Save' : 'Admit student',
      fields: fields,
      onSubmit: (v) async {
        final api = ref.read(apiClientProvider);
        int? id(String k) => int.tryParse(v[k] ?? '');

        if (_editing) {
          await api.put('/students/${s!.id}', data: {
            'first_name': v['first_name'],
            'last_name': v['last_name'],
            'gender': v['gender'],
            'date_of_birth': v['date_of_birth'],
            'school_class_id': id('school_class_id'),
            'stream_id': id('stream_id'),
          });
          return;
        }

        final guardian = v['guardian_id'] == 'new'
            ? {
                'name': v['guardian_name'],
                'phone': v['guardian_phone'],
                if ((v['guardian_email'] ?? '').isNotEmpty) 'email': v['guardian_email'],
                if ((v['guardian_occupation'] ?? '').isNotEmpty) 'occupation': v['guardian_occupation'],
                'relationship': v['relationship'],
                'is_primary': true,
              }
            : {'guardian_id': id('guardian_id'), 'relationship': v['relationship'], 'is_primary': true};

        await api.post('/students', data: {
          'student': {
            'first_name': v['first_name'],
            'last_name': v['last_name'],
            'gender': v['gender'],
            'date_of_birth': v['date_of_birth'],
            'admission_date': v['admission_date'],
            'academic_year_id': id('academic_year_id'),
            'school_class_id': id('school_class_id'),
            'stream_id': id('stream_id'),
          },
          'guardians': [guardian],
        });
      },
    );
  }
}
