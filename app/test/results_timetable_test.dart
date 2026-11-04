import 'package:elimugrid_app/features/parent/domain/parent_models.dart';
import 'package:elimugrid_app/features/parent/presentation/parent_providers.dart';
import 'package:elimugrid_app/features/parent/presentation/parent_screens.dart';
import 'package:elimugrid_app/features/timetable/domain/timetable_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

const _child = ChildSummary(id: 'c1', name: 'Neema Kileo', admissionNumber: 'S-1', status: 'active');

Widget _child_(Map<String, dynamic> results, Map<String, dynamic> timetable) => ProviderScope(
  overrides: [
    childBusProvider('c1').overrideWith((ref) => Stream.value(const ChildBus(assigned: false))),
    childResultsProvider(('c1', null)).overrideWith((ref) => Future.value(ChildResults.fromJson(results))),
    childTimetableProvider('c1').overrideWith((ref) => Future.value(TimetableData.fromJson(timetable))),
  ],
  child: const MaterialApp(home: ChildScreen(child: _child)),
);

void main() {
  testWidgets('an unpublished term shows nothing, a published one shows position, average and each subject', (tester) async {
    await tester.pumpWidget(_child_({'published': false, 'terms': []}, {'periods': [], 'entries': []}));
    await tester.tap(find.text('Results'));
    await tester.pumpAndSettle();

    expect(find.text('No results have been published yet.'), findsOneWidget);
  });

  testWidgets('a published result shows the position average grade and subject lines', (tester) async {
    await tester.pumpWidget(_child_({
      'published': true, 'terms': [], 'term': {'id': 1, 'name': 'Term 1'},
      'class': 'Form 1', 'class_size': 30, 'class_average': 65.4,
      'position': 3, 'average': 72.5, 'total': 145, 'grade': 'B', 'attendance_rate': 92,
      'exams': [], 'lines': [
        {'subject': 'Mathematics', 'percent': 80, 'grade': 'A', 'class_average': 60},
      ],
      'pdf_url': '/results/students/c1/pdf',
    }, {'periods': [], 'entries': []}));
    await tester.tap(find.text('Results'));
    await tester.pumpAndSettle();

    expect(find.text('3/30'), findsOneWidget);
    expect(find.text('72.5%'), findsOneWidget);
    expect(find.text('B'), findsWidgets);
    expect(find.text('Mathematics'), findsOneWidget);
    expect(find.text('80%'), findsOneWidget);
    expect(find.text('View report card (PDF)'), findsOneWidget);
  });

  testWidgets('the timetable tab shows the childs lessons', (tester) async {
    await tester.pumpWidget(_child_({'published': false, 'terms': []}, {
      'days': [1],
      'periods': [
        {'id': 1, 'name': 'Period 1', 'start_time': '08:00', 'end_time': '08:40', 'is_break': false},
      ],
      'entries': [
        {'day_of_week': 1, 'period_id': 1, 'subject': 'Mathematics', 'teacher': 'Mr Juma', 'class': 'Form 1', 'stream': 'Blue', 'room': null},
      ],
    }));
    await tester.tap(find.text('Timetable'));
    await tester.pumpAndSettle();

    expect(find.text('Mathematics'), findsOneWidget);
    expect(find.text('Mr Juma'), findsOneWidget);
  });

  testWidgets('no lessons scheduled shows the empty message', (tester) async {
    await tester.pumpWidget(_child_({'published': false, 'terms': []}, {'periods': [], 'entries': []}));
    await tester.tap(find.text('Timetable'));
    await tester.pumpAndSettle();

    expect(find.text('No lessons scheduled yet.'), findsOneWidget);
  });
}
