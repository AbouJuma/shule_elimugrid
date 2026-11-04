import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/platform/presentation/platform_dashboard_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

Map<String, dynamic> _dashboardJson() => {
  'data': {
    'stats': {'total_schools': 3, 'active_schools': 2, 'pending_schools': 0, 'suspended_schools': 1, 'total_students': 450, 'total_staff': 30},
    'onboarded_by_month': [
      for (final m in ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']) {'label': m, 'value': m == 'Sep' ? 3 : 0},
    ],
    'schools_by_package': [
      {'label': 'Gold', 'value': 2},
      {'label': 'No package', 'value': 1},
    ],
    'recent_schools': [
      {'id': 's1', 'name': 'Sunrise Academy', 'slug': 'sunrise', 'status': 'active', 'students_count': 300, 'created_at': '2026-09-01T00:00:00Z'},
    ],
  },
};

// FakeApi.get() wraps whatever is registered here as {'data': ...} itself — so this
// is the raw list PaginatedResult.fromJson expects at body['data'], not a full envelope.
List<Map<String, dynamic>> _schoolsList() => [
  {'id': 's1', 'name': 'Sunrise Academy', 'slug': 'sunrise', 'status': 'active', 'students_count': 300, 'subscription_package': {'id': 1, 'name': 'Gold'}},
  {'id': 's2', 'name': 'Twilight School', 'slug': 'twilight', 'status': 'suspended', 'students_count': 0},
];

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('the platform dashboard shows cross-tenant stats, charts and each school with a working status toggle', (tester) async {
    _tall(tester);
    final api = FakeApi({'/super-admin/dashboard': _dashboardJson()['data'] as Object, '/super-admin/schools': _schoolsList()});

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: Scaffold(body: PlatformDashboardBody())),
    ));
    await tester.pumpAndSettle();

    expect(find.text('3'), findsWidgets); // Total Schools (also appears once in the chart, for September)
    expect(find.text('450'), findsOneWidget); // Active Students
    expect(find.text('Sunrise Academy'), findsOneWidget);
    expect(find.text('Twilight School'), findsOneWidget);
    expect(find.text('Suspend'), findsOneWidget); // active school -> can be suspended
    expect(find.text('Activate'), findsOneWidget); // suspended school -> can be activated

    await tester.tap(find.text('Suspend'));
    await tester.pumpAndSettle();

    expect(api.calls.single.$1, 'PATCH');
    expect(api.calls.single.$2, '/super-admin/schools/s1/suspend');
  });

  testWidgets('no schools yet shows an empty state, not a blank screen', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/super-admin/dashboard': {
        'stats': {'total_schools': 0, 'active_schools': 0, 'pending_schools': 0, 'suspended_schools': 0, 'total_students': 0, 'total_staff': 0},
        'onboarded_by_month': [for (final m in ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']) {'label': m, 'value': 0}],
        'schools_by_package': <dynamic>[],
        'recent_schools': <dynamic>[],
      },
      '/super-admin/schools': <Map<String, dynamic>>[],
    });

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: const MaterialApp(home: Scaffold(body: PlatformDashboardBody())),
    ));
    await tester.pumpAndSettle();

    expect(find.text('No schools yet.'), findsOneWidget);
  });
}
