import 'package:elimugrid_app/core/network/api_exception.dart';
import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/attendance_scan/presentation/attendance_scan_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

class _RejectingApi extends FakeApi {
  @override
  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) async {
    calls.add(('POST', path, data));
    throw ApiException(message: 'No student or staff member found.', statusCode: 422);
  }
}

void main() {
  // These tests only exercise the "Type Code" tab, which is the default —
  // the camera tab (MobileScanner) needs a real camera platform
  // implementation this test environment doesn't provide, so it's covered
  // by manual verification instead, not a widget test.

  testWidgets('typing a code and pressing Enter shows who was marked present', (tester) async {
    _tall(tester);
    final api = FakeApi()
      ..postResult = {
        'data': {'type': 'student', 'name': 'Neema Test', 'sub_label': 'Form 1', 'status': 'present', 'already_marked': false},
      };

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: AttendanceScanScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Nothing scanned yet.'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'STU-0001');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();

    expect(find.text('Neema Test'), findsOneWidget);
    expect(find.textContaining('Form 1'), findsOneWidget);

    final call = api.calls.firstWhere((c) => c.$2 == '/attendance/scan');
    expect(call.$3?['code'], 'STU-0001');

    // The field clears, ready for the next scan.
    expect(find.text('STU-0001'), findsNothing);
  });

  testWidgets('an unrecognised code shows an error in the history list', (tester) async {
    _tall(tester);
    final api = _RejectingApi();

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: AttendanceScanScreen()),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'NOT-REAL');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();

    expect(find.text('No student or staff member found.'), findsOneWidget);
  });
}
