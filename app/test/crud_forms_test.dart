import 'package:elimugrid_app/core/network/api_client.dart';
import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/academics/academics_lookups.dart';
import 'package:elimugrid_app/features/guardians/domain/guardian_model.dart';
import 'package:elimugrid_app/features/guardians/presentation/guardian_form_screen.dart';
import 'package:elimugrid_app/features/transport/domain/trip_models.dart';
import 'package:elimugrid_app/features/transport/domain/vehicle_model.dart';
import 'package:elimugrid_app/features/transport/presentation/route_form_screen.dart';
import 'package:elimugrid_app/features/transport/presentation/vehicle_form_screen.dart';
import 'package:elimugrid_app/features/transport/presentation/vehicles_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

class FakeApi extends ApiClient {
  final List<(String method, String path, Map<String, dynamic>? data)> calls = [];

  Future<Map<String, dynamic>> _rec(String m, String p, Map<String, dynamic>? d) async {
    calls.add((m, p, d));

    return {'data': <String, dynamic>{}};
  }

  @override
  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) => _rec('POST', path, data);

  @override
  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) => _rec('PUT', path, data);

  @override
  Future<Map<String, dynamic>> delete(String path) => _rec('DELETE', path, null);
}

Widget _app(Widget child, List<Override> overrides) => ProviderScope(overrides: overrides, child: MaterialApp(home: child));

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('a guardian is edited with PUT to their id, blank optional fields sent as null', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(
      const GuardianFormScreen(guardian: GuardianModel(id: 5, name: 'Old Name', phone: '0700', email: 'old@x.test')),
      [apiClientProvider.overrideWithValue(api)],
    ));

    await tester.enterText(find.widgetWithText(TextFormField, 'Full name *'), 'New Name');
    await tester.enterText(find.widgetWithText(TextFormField, 'Email'), '');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final (method, path, body) = api.calls.single;
    expect(method, 'PUT');
    expect(path, '/guardians/5');
    expect(body!['name'], 'New Name');
    expect(body['email'], isNull);
    expect(body['occupation'], isNull);
  });

  testWidgets('a new guardian is created with POST', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const GuardianFormScreen(), [apiClientProvider.overrideWithValue(api)]));
    await tester.enterText(find.widgetWithText(TextFormField, 'Full name *'), 'Asha');
    await tester.enterText(find.widgetWithText(TextFormField, 'Phone *'), '0712');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.calls.single.$1, 'POST');
    expect(api.calls.single.$2, '/guardians');
  });

  testWidgets('a vehicle is created with the driver\'s uuid and a numeric seat count', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const VehicleFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      driverOptionsProvider.overrideWith((ref) async => [const DriverOption(id: 'uuid-of-james', name: 'James Driver')]),
      vehiclesListProvider.overrideWith((ref) async => <VehicleModel>[]),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Registration number *'), 'T 452 DKL');
    await tester.enterText(find.widgetWithText(TextFormField, 'Seats'), '29');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Driver'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('James Driver').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final (method, path, body) = api.calls.first;
    expect(method, 'POST');
    expect(path, '/vehicles');
    expect(body!['registration_number'], 'T 452 DKL');
    expect(body['capacity'], 29);
    expect(body['status'], 'active');
    expect(body['driver_user_id'], 'uuid-of-james');
  });

  testWidgets('editing a route sends the existing stop ids back, in order, with the new name', (tester) async {
    _tall(tester);
    final api = FakeApi();

    const route = TransportRouteModel(
      id: 'route-uuid',
      name: 'Kawe',
      status: 'active',
      stops: [
        RouteStopModel(id: 41, name: 'Second', sequence: 2, latitude: -6.74, longitude: 39.22, pickupTime: '06:40:00'),
        RouteStopModel(id: 40, name: 'First', sequence: 1, latitude: -6.73, longitude: 39.21, pickupTime: '06:30:00'),
      ],
    );

    await tester.pumpWidget(_app(const RouteFormScreen(route: route), [
      apiClientProvider.overrideWithValue(api),
      vehiclesListProvider.overrideWith((ref) async => <VehicleModel>[]),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Route name *'), 'Kawe – Morning');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final (method, path, body) = api.calls.first;
    expect(method, 'PUT');
    expect(path, '/transport-routes/route-uuid');
    expect(body!['name'], 'Kawe – Morning');

    final stops = (body['stops'] as List).cast<Map<String, dynamic>>();
    expect(stops.map((s) => s['id']), [40, 41], reason: 'stops are sent in sequence order with their ids, so the server updates them in place');
    expect(stops.map((s) => s['sequence']), [1, 2]);
    expect(stops.first['pickup_time'], '06:30', reason: 'seconds are trimmed to the H:i format the API validates');
    expect(stops.first['latitude'], -6.73);
  });

  testWidgets('a route with no stops cannot be saved', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const RouteFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      vehiclesListProvider.overrideWith((ref) async => <VehicleModel>[]),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Route name *'), 'Empty route');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.calls, isEmpty);
    expect(find.text('Add at least one stop.'), findsOneWidget);
  });
}
