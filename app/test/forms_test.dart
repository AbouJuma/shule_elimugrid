import 'package:elimugrid_app/core/network/api_client.dart';
import 'package:elimugrid_app/core/network/api_exception.dart';
import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/academics/academics_lookups.dart';
import 'package:elimugrid_app/features/guardians/domain/guardian_model.dart';
import 'package:elimugrid_app/features/students/presentation/student_form_screen.dart';
import 'package:elimugrid_app/shared/forms/entity_form.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

/// Records what the app would send instead of touching the network.
class FakeApi extends ApiClient {
  final List<(String method, String path, Map<String, dynamic>? data)> calls = [];
  ApiException? failWith;

  Future<Map<String, dynamic>> _record(String method, String path, Map<String, dynamic>? data) async {
    calls.add((method, path, data));
    if (failWith != null) throw failWith!;

    return {'data': <String, dynamic>{}};
  }

  @override
  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) => _record('POST', path, data);

  @override
  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) => _record('PUT', path, data);

  @override
  Future<Map<String, dynamic>> delete(String path) => _record('DELETE', path, null);
}

Widget _app(Widget child, {List<Override> overrides = const []}) =>
    ProviderScope(overrides: overrides, child: MaterialApp(home: child));

void _tallScreen(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  group('EntityFormScreen', () {
    testWidgets('will not submit until required fields are filled, and says which', (tester) async {
      _tallScreen(tester);
      var submitted = 0;

      await tester.pumpWidget(_app(EntityFormScreen(
        title: 'Test',
        fields: const [
          FieldSpec(key: 'name', label: 'Name', required: true),
          FieldSpec(key: 'gender', label: 'Gender', kind: FieldKind.choice, required: true, choices: [FieldChoice('m', 'Male'), FieldChoice('f', 'Female')]),
        ],
        onSubmit: (_) async => submitted++,
      )));

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(submitted, 0);
      expect(find.text('This field is required.'), findsNWidgets(2));
    });

    testWidgets('submits the values and closes when everything is valid', (tester) async {
      _tallScreen(tester);
      Map<String, String>? got;

      await tester.pumpWidget(_app(Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => EntityFormScreen(
                title: 'Test',
                fields: const [
                  FieldSpec(key: 'name', label: 'Name', required: true),
                  FieldSpec(key: 'gender', label: 'Gender', kind: FieldKind.choice, required: true, choices: [FieldChoice('m', 'Male'), FieldChoice('f', 'Female')]),
                ],
                onSubmit: (v) async => got = v,
              ),
            )),
            child: const Text('open'),
          ),
        ),
      )));

      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField), '  Neema  ');
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Female').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(got, {'name': 'Neema', 'gender': 'f'}, reason: 'values are trimmed and choices submit their value, not their label');
      expect(find.text('open'), findsOneWidget, reason: 'the form closed');
    });

    testWidgets('shows the server\'s validation message next to the right field and stays open', (tester) async {
      _tallScreen(tester);

      await tester.pumpWidget(_app(EntityFormScreen(
        title: 'Test',
        fields: const [FieldSpec(key: 'first_name', label: 'First name', serverKey: 'student.first_name')],
        onSubmit: (_) async => throw ApiException(message: 'The given data was invalid.', fieldErrors: {'student.first_name': ['That name is taken.']}),
      )));

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(find.text('That name is taken.'), findsOneWidget);
      expect(find.text('The given data was invalid.'), findsNothing, reason: 'a field-level error replaces the generic banner');
      expect(find.byType(EntityFormScreen), findsOneWidget);
    });

    testWidgets('shows a general error banner when the error is not about a field', (tester) async {
      _tallScreen(tester);

      await tester.pumpWidget(_app(EntityFormScreen(
        title: 'Test',
        fields: const [FieldSpec(key: 'name', label: 'Name')],
        onSubmit: (_) async => throw ApiException(message: 'You do not have permission.'),
      )));

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(find.text('You do not have permission.'), findsOneWidget);
    });

    testWidgets('a dependent field appears when its parent is chosen and is cleared when the parent changes', (tester) async {
      _tallScreen(tester);
      Map<String, String>? got;

      await tester.pumpWidget(_app(EntityFormScreen(
        title: 'Test',
        fields: [
          const FieldSpec(key: 'cls', label: 'Class', kind: FieldKind.choice, choices: [FieldChoice('1', 'Form 1'), FieldChoice('2', 'Form 2')]),
          FieldSpec(
            key: 'stream',
            label: 'Stream',
            kind: FieldKind.choice,
            choicesFor: (v) => v['cls'] == '1' ? const [FieldChoice('a', 'Blue')] : (v['cls'] == '2' ? const [FieldChoice('b', 'Red')] : const []),
            visibleWhen: (v) => (v['cls'] ?? '').isNotEmpty,
          ),
        ],
        onSubmit: (v) async => got = v,
      )));

      expect(find.text('Stream'), findsNothing);

      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Form 1').last);
      await tester.pumpAndSettle();
      expect(find.text('Stream'), findsOneWidget);

      await tester.tap(find.byType(DropdownButtonFormField<String>).last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Blue').last);
      await tester.pumpAndSettle();

      // Changing the class drops the now-invalid stream.
      await tester.tap(find.byType(DropdownButtonFormField<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Form 2').last);
      await tester.pumpAndSettle();

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(got, {'cls': '2', 'stream': ''});
    });
  });

  group('StudentFormScreen', () {
    List<Override> lookups(FakeApi api) => [
      apiClientProvider.overrideWithValue(api),
      yearOptionsProvider.overrideWith((ref) async => [const YearOption(id: 7, name: '2026/2027', isCurrent: true)]),
      classOptionsProvider.overrideWith((ref) async => [
        const ClassOption(id: 3, name: 'Form 1', streams: [StreamOption(id: 11, name: 'Blue')]),
      ]),
      guardianOptionsProvider.overrideWith((ref) async => [const GuardianModel(id: 5, name: 'Old Guardian', phone: '0700')]),
    ];

    testWidgets('admits a student with a new guardian and sends exactly what the API expects', (tester) async {
      _tallScreen(tester);
      final api = FakeApi();

      await tester.pumpWidget(_app(const StudentFormScreen(), overrides: lookups(api)));
      await tester.pumpAndSettle();

      final fields = find.byType(TextFormField);
      await tester.enterText(fields.at(0), 'Neema');
      await tester.enterText(fields.at(1), 'Kileo');

      await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Gender *'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Female').last);
      await tester.pumpAndSettle();

      // Date of birth: accept the picker's default (about ten years ago).
      await tester.tap(find.text('Choose a date'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();

      // Class → the stream field appears.
      await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Class'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Form 1').last);
      await tester.pumpAndSettle();
      expect(find.text('Stream'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextFormField, 'Guardian name *'), 'Asha Kileo');
      await tester.enterText(find.widgetWithText(TextFormField, 'Guardian phone *'), '0712345678');

      await tester.tap(find.text('Admit student'));
      await tester.pumpAndSettle();

      expect(api.calls, hasLength(1));
      final (method, path, body) = api.calls.single;
      expect(method, 'POST');
      expect(path, '/students');

      final student = body!['student'] as Map<String, dynamic>;
      expect(student['first_name'], 'Neema');
      expect(student['last_name'], 'Kileo');
      expect(student['gender'], 'female');
      expect(student['academic_year_id'], 7, reason: 'the current year is preselected');
      expect(student['school_class_id'], 3);
      expect(student['stream_id'], isNull);
      expect(student['date_of_birth'], matches(RegExp(r'^\d{4}-01-01$')));

      final guardian = (body['guardians'] as List).single as Map<String, dynamic>;
      expect(guardian['name'], 'Asha Kileo');
      expect(guardian['phone'], '0712345678');
      expect(guardian['relationship'], 'guardian');
      expect(guardian['is_primary'], true);
    });

    testWidgets('an existing guardian can be chosen instead of typing a new one', (tester) async {
      _tallScreen(tester);
      final api = FakeApi();

      await tester.pumpWidget(_app(const StudentFormScreen(), overrides: lookups(api)));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField).at(0), 'Juma');
      await tester.enterText(find.byType(TextFormField).at(1), 'Bakari');
      await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Gender *'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Male').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Choose a date'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('New guardian…'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Old Guardian · 0700').last);
      await tester.pumpAndSettle();

      // The new-guardian fields are gone, so they're neither shown nor required.
      expect(find.text('Guardian name *'), findsNothing);

      await tester.tap(find.text('Admit student'));
      await tester.pumpAndSettle();

      final guardian = ((api.calls.single.$3!['guardians']) as List).single as Map<String, dynamic>;
      expect(guardian['guardian_id'], 5);
      expect(guardian.containsKey('name'), isFalse);
    });

    testWidgets('the server\'s "phone is required" style errors appear on the guardian fields', (tester) async {
      _tallScreen(tester);
      final api = FakeApi()..failWith = ApiException(message: 'invalid', fieldErrors: {'guardians.0.phone': ['The phone is not valid.']});

      await tester.pumpWidget(_app(const StudentFormScreen(), overrides: lookups(api)));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextFormField).at(0), 'Juma');
      await tester.enterText(find.byType(TextFormField).at(1), 'Bakari');
      await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Gender *'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Male').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Choose a date'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextFormField, 'Guardian name *'), 'Asha');
      await tester.enterText(find.widgetWithText(TextFormField, 'Guardian phone *'), '12');

      await tester.tap(find.text('Admit student'));
      await tester.pumpAndSettle();

      expect(find.text('The phone is not valid.'), findsOneWidget);
    });
  });
}
