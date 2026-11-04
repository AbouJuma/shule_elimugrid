import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:elimugrid_app/main.dart';

void main() {
  testWidgets('App boots to the splash screen before auth resolves', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const ProviderScope(child: ElimuGridApp()));
    await tester.pump();

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });
}
