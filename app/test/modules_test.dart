import 'package:elimugrid_app/core/network/api_client.dart';
import 'package:elimugrid_app/core/providers.dart';
import 'package:elimugrid_app/features/academics/academics_lookups.dart';
import 'package:elimugrid_app/features/academics/academics_screens.dart';
import 'package:elimugrid_app/features/fees/fee_setup_screens.dart';
import 'package:elimugrid_app/features/hostel/hostel_screens.dart';
import 'package:elimugrid_app/features/hr/staff_screens.dart';
import 'package:elimugrid_app/features/inventory/inventory_screens.dart';
import 'package:elimugrid_app/features/library/library_screens.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

/// Answers GETs from a canned table and records every write.
class FakeApi extends ApiClient {
  FakeApi([this.gets = const {}]);

  final Map<String, Object> gets;
  final List<(String method, String path, Map<String, dynamic>? data)> calls = [];
  Map<String, dynamic> postResult = {'data': <String, dynamic>{}};

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) async {
    final v = gets[path];

    return {'data': v ?? <dynamic>[]};
  }

  @override
  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) async {
    calls.add(('POST', path, data));

    return postResult;
  }

  @override
  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) async {
    calls.add(('PUT', path, data));

    return {'data': <String, dynamic>{}};
  }

  @override
  Future<Map<String, dynamic>> delete(String path) async {
    calls.add(('DELETE', path, null));

    return {'data': <String, dynamic>{}};
  }

  @override
  Future<Map<String, dynamic>> patch(String path, {Map<String, dynamic>? data}) async {
    calls.add(('PATCH', path, data));

    return {'data': <String, dynamic>{}};
  }
}

Widget _app(Widget child, List<Override> overrides) => ProviderScope(overrides: overrides, child: MaterialApp(home: child));

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(900, 3200);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('staff are onboarded with the user/profile payload the API expects and the temporary password is shown once', (tester) async {
    _tall(tester);
    final api = FakeApi()..postResult = {'data': {}, 'temporary_password': 'Tmp-Pass-123'};

    await tester.pumpWidget(_app(const StaffFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      roleNamesProvider.overrideWith((ref) async => ['Teacher', 'Accountant']),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Full name *'), 'Amina Juma');
    await tester.enterText(find.widgetWithText(TextFormField, 'Email *'), 'amina@school.test');
    // Required when creating: it becomes their first login password.
    await tester.enterText(find.widgetWithText(TextFormField, 'Phone *'), '0712345678');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Role *'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Teacher').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'Job title'), 'Maths teacher');

    await tester.tap(find.text('Create account'));
    // The form waits (spinner running) for the password dialog to be dismissed, so it never 'settles'.
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));

    final (method, path, body) = api.calls.single;
    expect(method, 'POST');
    expect(path, '/staff');
    expect((body!['user'] as Map)['email'], 'amina@school.test');
    expect((body['user'] as Map)['phone'], '0712345678');
    expect((body['user'] as Map)['role'], 'Teacher');
    expect((body['profile'] as Map)['job_title'], 'Maths teacher');
    expect((body['profile'] as Map)['employment_type'], 'full_time');

    expect(find.text('Tmp-Pass-123'), findsOneWidget, reason: 'the one-time password is shown to whoever created the account');

    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(find.text('Tmp-Pass-123'), findsNothing);
  });

  testWidgets('phone is required to create a staff account but not to edit one', (tester) async {
    _tall(tester);
    final api = FakeApi()..postResult = {'data': {}, 'temporary_password': '0712345678'};

    await tester.pumpWidget(_app(const StaffFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      roleNamesProvider.overrideWith((ref) async => ['Teacher']),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Full name *'), 'No Phone');
    await tester.enterText(find.widgetWithText(TextFormField, 'Email *'), 'nophone@school.test');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Role *'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Teacher').last);
    await tester.pumpAndSettle();

    // Phone left blank on purpose.
    await tester.tap(find.text('Create account'));
    await tester.pumpAndSettle();

    expect(api.calls, isEmpty, reason: 'client-side validation should block submission without a phone');
    expect(find.text('This field is required.'), findsOneWidget);
  });

  testWidgets('editing a staff member can rename them, change their email, and reassign their role', (tester) async {
    _tall(tester);
    final api = FakeApi();

    final existing = {
      'id': 'staff-uuid-1',
      'name': 'Grace Teacher',
      'email': 'grace@school.test',
      'phone': null,
      'status': 'active',
      'roles': ['Teacher'],
      'profile': {'job_title': 'Maths teacher', 'employment_type': 'full_time', 'date_joined': '2026-01-15'},
    };

    await tester.pumpWidget(_app(StaffFormScreen(staff: existing), [
      apiClientProvider.overrideWithValue(api),
      roleNamesProvider.overrideWith((ref) async => ['Teacher', 'HR', 'Accountant']),
    ]));
    await tester.pumpAndSettle();

    // Starts pre-filled with the current role, not a blank picker.
    expect(find.widgetWithText(DropdownButtonFormField<String>, 'Role *'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextFormField, 'Email *'), 'grace.new@school.test');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Role *'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('HR').last);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final (method, path, body) = api.calls.single;
    expect(method, 'PUT');
    expect(path, '/staff/staff-uuid-1');
    expect(body!['email'], 'grace.new@school.test');
    expect(body['role'], 'HR');
    expect(body['name'], 'Grace Teacher');
  });

  testWidgets('an inventory item is created with its opening quantity and category id', (tester) async {
    _tall(tester);
    final api = FakeApi({'/inventory/categories': [{'id': 4, 'name': 'Stationery', 'items_count': 0}]});

    await tester.pumpWidget(_app(const ItemFormScreen(), [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Item name *'), 'Chalk');
    await tester.enterText(find.widgetWithText(TextFormField, 'Unit (box, kg, piece…) *'), 'box');
    await tester.enterText(find.widgetWithText(TextFormField, 'Quantity in stock now'), '12');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Category'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Stationery').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final body = api.calls.single.$3!;
    expect(api.calls.single.$2, '/inventory/items');
    expect(body['inventory_category_id'], 4);
    expect(body['opening_quantity'], 12.0);
    expect(body['unit'], 'box');
  });

  testWidgets('lending a book searches students, submits the chosen student\'s uuid, and blocks an empty choice', (tester) async {
    _tall(tester);
    final api = FakeApi({
      '/students': [
        {'id': 'uuid-neema', 'full_name': 'Neema Kileo', 'admission_number': 'STU-1'},
        {'id': 'uuid-juma', 'full_name': 'Juma Bakari', 'admission_number': 'STU-2'},
      ],
    });

    await tester.pumpWidget(_app(LendBookScreen(book: const {'id': 9, 'title': 'Kamusi'}), [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();

    // No student chosen → refused with the required-field message, nothing sent.
    await tester.tap(find.text('Lend book'));
    await tester.pumpAndSettle();
    expect(api.calls, isEmpty);
    expect(find.text('This field is required.'), findsOneWidget);

    await tester.tap(find.text('Tap to search…'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Juma Bakari · STU-2'));
    await tester.pumpAndSettle();
    expect(find.text('Juma Bakari · STU-2'), findsOneWidget, reason: 'the chosen student\'s name is shown in the field');

    await tester.tap(find.text('Lend book'));
    await tester.pumpAndSettle();

    final (method, path, body) = api.calls.single;
    expect(method, 'POST');
    expect(path, '/library/books/9/borrow');
    expect(body!['student_id'], 'uuid-juma');
    expect(body['due_date'], matches(RegExp(r'^\d{4}-\d{2}-\d{2}$')));
  });

  testWidgets('a subject is saved with its classes chosen from a multi-select as integer ids', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const SubjectFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      classOptionsProvider.overrideWith((ref) async => [const ClassOption(id: 3, name: 'Form 1'), const ClassOption(id: 4, name: 'Form 2'), const ClassOption(id: 5, name: 'Form 3')]),
      subjectOptionsProvider.overrideWith((ref) async => <SubjectOption>[]),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Subject name *'), 'History');
    await tester.tap(find.text('None selected'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Form 1'));
    await tester.tap(find.text('Form 3'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Done'));
    await tester.pumpAndSettle();
    expect(find.text('Form 1, Form 3'), findsOneWidget);

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final body = api.calls.single.$3!;
    expect(api.calls.single.$2, '/subjects');
    expect(body['name'], 'History');
    expect(body['code'], isNull);
    expect(body['school_class_ids'], [3, 5]);
  });

  testWidgets('a subject being edited starts with its classes already ticked', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const SubjectFormScreen(subject: {'id': 7, 'name': 'Maths', 'code': 'MAT', 'class_ids': [4]}), [
      apiClientProvider.overrideWithValue(api),
      classOptionsProvider.overrideWith((ref) async => [const ClassOption(id: 3, name: 'Form 1'), const ClassOption(id: 4, name: 'Form 2')]),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('Form 2'), findsOneWidget);

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(api.calls.single.$1, 'PUT');
    expect(api.calls.single.$2, '/subjects/7');
    expect(api.calls.single.$3!['school_class_ids'], [4]);
  });

  testWidgets('a term is created for the current year with the current-term flag', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const TermFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      yearOptionsProvider.overrideWith((ref) async => [const YearOption(id: 2, name: '2026/2027', isCurrent: true)]),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Term name *'), 'Term 1');

    for (final label in ['Starts *', 'Ends *']) {
      await tester.tap(find.widgetWithText(InputDecorator, label));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
    }

    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Current term?'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Yes — this is the current term').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final body = api.calls.single.$3!;
    expect(api.calls.single.$2, '/terms');
    expect(body['academic_year_id'], 2);
    expect(body['name'], 'Term 1');
    expect(body['is_current'], true);
  });

  testWidgets('a fee structure needs complete items and sends numeric amounts', (tester) async {
    _tall(tester);
    final api = FakeApi();

    await tester.pumpWidget(_app(const FeeStructureFormScreen(), [
      apiClientProvider.overrideWithValue(api),
      yearOptionsProvider.overrideWith((ref) async => [const YearOption(id: 2, name: '2026/2027', isCurrent: true)]),
      classOptionsProvider.overrideWith((ref) async => [const ClassOption(id: 3, name: 'Form 1')]),
    ]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Name *'), 'Form 1 fees');
    await tester.tap(find.widgetWithText(DropdownButtonFormField<String>, 'Class *'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Form 1').last);
    await tester.pumpAndSettle();

    // An item with a name but no amount is rejected.
    await tester.enterText(find.widgetWithText(TextField, 'Item (e.g. Tuition)'), 'Tuition');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(api.calls, isEmpty);
    expect(find.text('Every fee item needs a name and an amount.'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'Amount'), '500000');
    await tester.tap(find.text('Add item'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'Item (e.g. Tuition)').last, 'Uniform');
    await tester.enterText(find.widgetWithText(TextField, 'Amount').last, '60000');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final body = api.calls.single.$3!;
    expect(api.calls.single.$2, '/fee-structures');
    expect(body['academic_year_id'], 2);
    expect(body['school_class_id'], 3);
    expect(body['items'], [
      {'name': 'Tuition', 'amount': 500000.0},
      {'name': 'Uniform', 'amount': 60000.0},
    ]);
  });

  testWidgets('a hostel room is created against the chosen hostel', (tester) async {
    _tall(tester);
    final api = FakeApi({'/hostel/hostels': [{'id': 8, 'name': 'Baobab', 'gender': 'male', 'rooms': 0, 'capacity': 0, 'occupied': 0, 'warden': null}]});

    await tester.pumpWidget(_app(const RoomFormScreen(hostelId: 8), [apiClientProvider.overrideWithValue(api)]));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Room number *'), 'A1');
    await tester.enterText(find.widgetWithText(TextFormField, 'Beds *'), '6');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    final body = api.calls.single.$3!;
    expect(api.calls.single.$2, '/hostel/rooms');
    expect(body, {'hostel_id': 8, 'room_number': 'A1', 'capacity': 6});
  });
}
