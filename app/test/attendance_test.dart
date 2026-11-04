import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/attendance/domain/attendance_models.dart';
import 'package:elimugrid_app/features/attendance/presentation/attendance_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

Map<String, dynamic> _register({int? subjectId}) => {
  'school_class': {'id': 1, 'name': 'Form 1'},
  'date': '2026-02-01',
  'subject_id': subjectId,
  'students': [
    {'id': 10, 'name': 'Amina Juma', 'admission_number': 'STU-0001', 'status': null, 'remarks': null},
  ],
};

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('a class offering subjects shows a By Class tab plus one per subject, and saving under a subject tab sends its id', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/attendance/classes': [
        {
          'id': 1,
          'name': 'Form 1',
          'subjects': [
            {'id': 5, 'name': 'Mathematics'},
          ],
        },
      ],
      '/attendance': _register(),
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: AttendanceScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.text('By Class'), findsOneWidget);
    expect(find.text('Mathematics'), findsOneWidget);

    await tester.tap(find.text('Mathematics'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save Attendance'));
    await tester.pumpAndSettle();

    final saveCall = api.calls.firstWhere((c) => c.$1 == 'POST' && c.$2 == '/attendance');
    expect(saveCall.$3?['subject_id'], 5);

    // Back to "By Class" clears the subject scoping.
    await tester.tap(find.text('By Class'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save Attendance'));
    await tester.pumpAndSettle();

    final secondSaveCall = api.calls.lastWhere((c) => c.$1 == 'POST' && c.$2 == '/attendance');
    expect(secondSaveCall.$3?.containsKey('subject_id'), isFalse);
  });

  testWidgets('a class with no subjects shows no tab row at all', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/attendance/classes': [
        {'id': 1, 'name': 'Form 1', 'subjects': <dynamic>[]},
      ],
      '/attendance': _register(),
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: AttendanceScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.text('By Class'), findsNothing);
  });

  testWidgets('a locked register disables editing and shows an Unlock button, which posts to /attendance/unlock', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/attendance/classes': [
        {'id': 1, 'name': 'Form 1', 'subjects': <dynamic>[]},
      ],
      '/attendance': {
        'school_class': {'id': 1, 'name': 'Form 1'},
        'date': '2026-02-01',
        'subject_id': null,
        'locked': true,
        'locked_by': 'Mr Juma',
        'can_unlock': true,
        'students': [
          {'id': 10, 'name': 'Amina Juma', 'admission_number': 'STU-0001', 'status': 'present', 'remarks': null},
        ],
      },
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: AttendanceScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.textContaining('Locked by Mr Juma'), findsOneWidget);
    expect(find.text('Save Attendance'), findsNothing);
    final dropdown = tester.widget<DropdownButton<AttendanceStatus>>(find.byType(DropdownButton<AttendanceStatus>));
    expect(dropdown.onChanged, isNull);

    await tester.tap(find.text('Unlock to Edit'));
    await tester.pumpAndSettle();

    final unlockCall = api.calls.firstWhere((c) => c.$1 == 'POST' && c.$2 == '/attendance/unlock');
    expect(unlockCall.$3?['school_class_id'], 1);
  });

  testWidgets('a locked register with no unlock permission shows a plain notice instead of a button', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/attendance/classes': [
        {'id': 1, 'name': 'Form 1', 'subjects': <dynamic>[]},
      ],
      '/attendance': {
        'school_class': {'id': 1, 'name': 'Form 1'},
        'date': '2026-02-01',
        'subject_id': null,
        'locked': true,
        'locked_by': 'Mr Juma',
        'can_unlock': false,
        'students': [
          {'id': 10, 'name': 'Amina Juma', 'admission_number': 'STU-0001', 'status': 'present', 'remarks': null},
        ],
      },
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: AttendanceScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Unlock to Edit'), findsNothing);
    expect(find.textContaining('Ask an administrator'), findsOneWidget);
  });
}
