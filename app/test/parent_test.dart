import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/guardians/domain/guardian_model.dart';
import 'package:elimugrid_app/features/guardians/presentation/guardian_login_actions.dart';
import 'package:elimugrid_app/features/parent/domain/parent_models.dart';
import 'package:elimugrid_app/features/parent/presentation/parent_providers.dart';
import 'package:elimugrid_app/features/parent/presentation/parent_screens.dart';
import 'package:elimugrid_app/features/profile/presentation/change_password_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

import 'modules_test.dart' show FakeApi;

const _child = ChildSummary(id: 'c1', name: 'Neema Kileo', admissionNumber: 'S-1', status: 'active', className: 'Form 1', route: 'Kawe route', stop: 'Mwenge');

Map<String, dynamic> _stop(int id, String name, int seq, double lat, double lng, {String? arrived, bool next = false}) => {
  'id': id, 'name': name, 'sequence': seq, 'latitude': lat, 'longitude': lng, 'arrived_at': arrived, 'is_next': next,
};

Map<String, dynamic> _live(Map<String, dynamic> mine) => {
  'assigned': true,
  'running': true,
  'route': 'Kawe route',
  'stop': {'id': 2, 'name': 'Mwenge', 'latitude': -6.744, 'longitude': 39.228},
  'vehicle': 'T 452 DKL',
  'mine': mine,
  'trip': {
    'id': 't1', 'status': 'in_progress', 'direction': 'morning', 'started_at': '2026-09-27T05:30:00Z',
    'vehicle': {'registration_number': 'T 452 DKL', 'driver': {'name': 'Juma'}},
    'route': {'id': 'r1', 'name': 'Kawe route'},
    'progress': {'visited': 1, 'total': 3},
    'stops': [
      _stop(1, 'Kawe', 1, -6.73, 39.22, arrived: '2026-09-27T05:40:00Z'),
      _stop(2, 'Mwenge', 2, -6.744, 39.228, next: true),
      _stop(3, 'Mikocheni', 3, -6.761, 39.242),
    ],
    'position': {'latitude': -6.73, 'longitude': 39.22, 'speed_kmh': 30, 'recorded_at': '2026-09-27T05:41:00Z'},
    'trail': [[-6.73, 39.22]],
  },
};

Widget _bus(Map<String, dynamic> json) => ProviderScope(
  overrides: [childBusProvider('c1').overrideWith((ref) => Stream.value(ChildBus.fromJson(json)))],
  child: const MaterialApp(home: ChildScreen(child: _child)),
);

void main() {
  testWidgets('a parent is told how long until the bus reaches their child\'s stop', (tester) async {
    await tester.pumpWidget(_bus(_live({'state': 'coming', 'stops_away': 0, 'distance_m': 2330, 'eta_minutes': 5})));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.textContaining('The bus is next to reach Mwenge — about 5 min.'), findsOneWidget);
    expect(find.textContaining('T 452 DKL'), findsWidgets);
  });

  testWidgets('with stops before theirs the parent sees how many', (tester) async {
    await tester.pumpWidget(_bus(_live({'state': 'coming', 'stops_away': 2, 'distance_m': 4000, 'eta_minutes': 12})));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.textContaining('The bus reaches Mwenge in about 12 min (2 stops before it).'), findsOneWidget);
  });

  testWidgets('one stop before theirs reads "1 stop", not "1 stops"', (tester) async {
    await tester.pumpWidget(_bus(_live({'state': 'coming', 'stops_away': 1, 'distance_m': 3000, 'eta_minutes': 8})));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.textContaining('(1 stop before it)'), findsOneWidget);
  });

  testWidgets('once the bus has been at their stop the parent is told when', (tester) async {
    await tester.pumpWidget(_bus(_live({'state': 'passed', 'arrived_at': '2026-09-27T05:50:00Z'})));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.textContaining('The bus was at Mwenge at'), findsOneWidget);
  });

  testWidgets('when no trip is running the parent sees the stop and times, and no map', (tester) async {
    await tester.pumpWidget(_bus({
      'assigned': true, 'running': false, 'route': 'Kawe route', 'vehicle': 'T 452 DKL',
      'stop': {'id': 2, 'name': 'Mwenge', 'pickup_time': '06:45:00', 'dropoff_time': '16:10:00'},
    }));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('The bus is not on a trip right now.'), findsOneWidget);
    expect(find.textContaining('06:45'), findsOneWidget);
    expect(find.textContaining('16:10'), findsOneWidget);
  });

  testWidgets('a child without a bus route is told so', (tester) async {
    await tester.pumpWidget(_bus({'assigned': false}));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('This child does not use the school bus.'), findsOneWidget);
  });

  testWidgets('the fees tab shows the balance and each invoice', (tester) async {
    final api = FakeApi({
      '/my/children/c1/fees': {
        'currency': 'TZS', 'total': '500000', 'paid': '200000', 'balance': '300000',
        'invoices': [
          {'id': 'i1', 'invoice_number': 'INV-1', 'control_number': 'CN-1', 'total': '500000', 'paid': '200000', 'balance': '300000', 'status': 'partial', 'due_date': null},
        ],
      },
    });

    await tester.pumpWidget(ProviderScope(
      overrides: <Override>[
        apiClientProvider.overrideWithValue(api),
        childBusProvider('c1').overrideWith((ref) => Stream.value(const ChildBus(assigned: false))),
      ],
      child: const MaterialApp(home: ChildScreen(child: _child)),
    ));
    await tester.pump();
    await tester.tap(find.text('Fees'));
    await tester.pumpAndSettle();

    expect(find.text('TZS 300,000.00'), findsWidgets);
    expect(find.text('INV-1'), findsOneWidget);
  });

  testWidgets('a mismatch between the two new passwords is caught before anything is sent', (tester) async {
    final api = FakeApi();

    await tester.pumpWidget(ProviderScope(overrides: [apiClientProvider.overrideWithValue(api)], child: const MaterialApp(home: ChangePasswordScreen())));
    await tester.enterText(find.widgetWithText(TextFormField, 'Current password *'), 'old-pass');
    await tester.enterText(find.widgetWithText(TextFormField, 'New password *'), 'NewPass1234');
    await tester.enterText(find.widgetWithText(TextFormField, 'Confirm new password *'), 'Different123');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(find.text('The two passwords do not match.'), findsOneWidget);
    expect(api.calls, isEmpty);
  });

  testWidgets('a matching new password is sent with its confirmation', (tester) async {
    final api = FakeApi();

    await tester.pumpWidget(ProviderScope(overrides: [apiClientProvider.overrideWithValue(api)], child: MaterialApp(home: Builder(builder: (c) => Scaffold(body: TextButton(onPressed: () => Navigator.of(c).push(MaterialPageRoute(builder: (_) => const ChangePasswordScreen())), child: const Text('open')))))));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Current password *'), 'old-pass');
    await tester.enterText(find.widgetWithText(TextFormField, 'New password *'), 'NewPass1234');
    await tester.enterText(find.widgetWithText(TextFormField, 'Confirm new password *'), 'NewPass1234');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.calls.single.$1, 'PUT');
    expect(api.calls.single.$2, '/auth/password');
    expect(api.calls.single.$3, {'current_password': 'old-pass', 'password': 'NewPass1234', 'password_confirmation': 'NewPass1234'});
  });

  testWidgets('creating a parent login shows the temporary password once', (tester) async {
    final api = FakeApi()..postResult = {'data': {'login_email': 'asha@parent.test'}, 'temporary_password': 'Zx81QwErTy55'};
    const guardian = GuardianModel(id: 7, name: 'Asha Kileo', phone: '0712345678', email: 'asha@parent.test');

    await tester.pumpWidget(ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        home: Scaffold(
          body: Consumer(builder: (context, ref, _) => TextButton(onPressed: () => manageGuardianLogin(context, ref, guardian), child: const Text('go'))),
        ),
      ),
    ));
    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    expect(api.calls.single.$2, '/guardians/7/account');
    expect(find.text('Zx81QwErTy55'), findsOneWidget);
    expect(find.textContaining('asha@parent.test'), findsOneWidget);
  });
}
