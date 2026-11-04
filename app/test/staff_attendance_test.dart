import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/staff_attendance/presentation/staff_attendance_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

String _today() {
  final now = DateTime.now();
  return '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
}

void main() {
  testWidgets('the staff register lists active staff and saving posts their statuses', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/staff-attendance': {
        'date': _today(),
        'locked': false,
        'locked_by': null,
        'can_unlock': false,
        'staff': [
          {'id': 3, 'name': 'Carol Teacher', 'employee_number': 'EMP-00001', 'job_title': 'Teacher', 'status': null},
        ],
      },
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: StaffAttendanceScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Carol Teacher'), findsOneWidget);
    expect(find.textContaining('EMP-00001'), findsOneWidget);

    await tester.tap(find.text('Save Attendance'));
    await tester.pumpAndSettle();

    final call = api.calls.firstWhere((c) => c.$2 == '/staff-attendance');
    expect(call.$3?['date'], _today());
  });

  testWidgets('a locked staff register shows a banner and disables editing', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/staff-attendance': {
        'date': '2026-02-01',
        'locked': true,
        'locked_by': 'Admin',
        'can_unlock': true,
        'staff': [
          {'id': 3, 'name': 'Carol Teacher', 'employee_number': 'EMP-00001', 'job_title': 'Teacher', 'status': 'present'},
        ],
      },
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: StaffAttendanceScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.textContaining('Locked by Admin'), findsOneWidget);
    expect(find.text('Save Attendance'), findsNothing);
    expect(find.text('Unlock to Edit'), findsOneWidget);
  });
}
