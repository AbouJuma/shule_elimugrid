import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/students/domain/student_model.dart';
import 'package:elimugrid_app/features/students/presentation/student_login_actions.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

const _student = StudentModel(
  id: 's1', admissionNumber: 'STU-1', firstName: 'Juma', lastName: 'Ali', fullName: 'Juma Ali', gender: 'male', status: 'active',
);

void main() {
  testWidgets('creating a student login asks for an email and shows the temporary password once', (tester) async {
    final api = FakeApi()..postResult = {'data': {'login_email': 'juma@student.test'}, 'temporary_password': 'Ab12Cd34Ef56'};

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        home: Scaffold(body: Consumer(builder: (context, ref, _) => TextButton(onPressed: () => manageStudentLogin(context, ref, _student), child: const Text('go')))),
      ),
    ));
    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'juma@student.test');
    await tester.tap(find.text('Create student login'));
    await tester.pumpAndSettle();

    expect(api.calls.single.$2, '/students/s1/account');
    expect(api.calls.single.$3, {'email': 'juma@student.test'});
    expect(find.text('Ab12Cd34Ef56'), findsOneWidget);
  });

  testWidgets('resetting a student who already has a login confirms first, with no email prompt', (tester) async {
    const withLogin = StudentModel(
      id: 's1', admissionNumber: 'STU-1', firstName: 'Juma', lastName: 'Ali', fullName: 'Juma Ali', gender: 'male', status: 'active', hasLogin: true,
    );
    final api = FakeApi()..postResult = {'data': <String, dynamic>{}, 'temporary_password': 'Zz99Yy88Xx77'};

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        home: Scaffold(body: Consumer(builder: (context, ref, _) => TextButton(onPressed: () => manageStudentLogin(context, ref, withLogin), child: const Text('go')))),
      ),
    ));
    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    expect(find.byType(TextField), findsNothing);
    await tester.tap(find.widgetWithText(FilledButton, 'Reset password'));
    await tester.pumpAndSettle();

    expect(find.text('Zz99Yy88Xx77'), findsOneWidget);
    expect(api.calls.single.$2, '/students/s1/account/reset');
  });
}
