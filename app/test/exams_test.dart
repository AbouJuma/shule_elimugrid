import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/academics/academics_lookups.dart';
import 'package:elimugrid_app/features/exams/presentation/exam_form_screens.dart';
import 'package:elimugrid_app/features/exams/presentation/marks_entry_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('an exam is created for several classes chosen from a multi-select as integer ids', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(api),
        classOptionsProvider.overrideWith((ref) async => [
          const ClassOption(id: 1, name: 'Form 1'),
          const ClassOption(id: 2, name: 'Form 2'),
        ]),
        yearOptionsProvider.overrideWith((ref) async => [const YearOption(id: 9, name: '2026/2027', isCurrent: true)]),
      ],
      child: const MaterialApp(home: ExamFormScreen()),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Exam name *'), 'Shared Mid-Term');
    await tester.tap(find.text('None selected'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Form 1'));
    await tester.tap(find.text('Form 2'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(find.text('Form 1, Form 2'), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, 'Create exam'));
    await tester.pumpAndSettle();

    final call = api.calls.single;
    expect(call.$2, '/exams');
    expect(call.$3!['school_class_ids'], [1, 2]);
  });

  testWidgets('recording marks posts the chosen class alongside the students entered', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/exams/1/subjects/5/marks': {
        'exam': {'id': 1, 'name': 'Mid-Term'},
        'subject': {'id': 5, 'name': 'Mathematics'},
        'school_class': {'id': 2, 'name': 'Form 2'},
        'max_marks': 100,
        'students': [
          {'id': 10, 'name': 'Amina Juma', 'admission_number': 'STU-0001', 'marks_obtained': null},
        ],
      },
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: MarksEntryScreen(examId: 1, subjectId: 5, classId: 2)),
    ));
    await tester.pumpAndSettle();

    // The register is scoped to the class picked before navigating here.
    expect(find.text('Mid-Term · Form 2 · Max marks: 100'), findsOneWidget);

    await tester.enterText(find.byType(TextField), '88');
    await tester.tap(find.text('Save Marks'));
    await tester.pumpAndSettle();

    final call = api.calls.single;
    expect(call.$2, '/exams/1/subjects/5/marks');
    expect(call.$3!['school_class_id'], 2);
    expect(call.$3!['records'], [
      {'student_id': 10, 'marks_obtained': 88},
    ]);
  });
}
