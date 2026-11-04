import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import '../../core/providers.dart';
import '../../shared/forms/entity_form.dart' show FieldChoice;
import '../guardians/domain/guardian_model.dart';

/// Reference lists the create/edit forms pick from (years, classes with their
/// streams, subjects, guardians, drivers). Each is fetched once per open
/// form and dropped afterwards (autoDispose).

class StreamOption {
  const StreamOption({required this.id, required this.name});

  final int id;
  final String name;
}

class ClassOption {
  const ClassOption({required this.id, required this.name, this.streams = const []});

  final int id;
  final String name;
  final List<StreamOption> streams;
}

class YearOption {
  const YearOption({required this.id, required this.name, required this.isCurrent});

  final int id;
  final String name;
  final bool isCurrent;
}

class SubjectOption {
  const SubjectOption({required this.id, required this.name});

  final int id;
  final String name;
}

class DriverOption {
  const DriverOption({required this.id, required this.name});

  /// The user's uuid (the API accepts it for `driver_user_id`).
  final String id;
  final String name;
}

List<Map<String, dynamic>> _rows(Map<String, dynamic> body) => (body['data'] as List<dynamic>).cast<Map<String, dynamic>>();

final classOptionsProvider = FutureProvider.autoDispose<List<ClassOption>>((ref) async {
  final body = await ref.watch(apiClientProvider).get('/classes');

  return _rows(body)
      .map((c) => ClassOption(
            id: c['id'] as int,
            name: c['name'] as String,
            streams: ((c['streams'] as List<dynamic>?) ?? const [])
                .cast<Map<String, dynamic>>()
                .map((s) => StreamOption(id: s['id'] as int, name: s['name'] as String))
                .toList(),
          ))
      .toList();
});

final yearOptionsProvider = FutureProvider.autoDispose<List<YearOption>>((ref) async {
  final body = await ref.watch(apiClientProvider).get('/academic-years');

  return _rows(body).map((y) => YearOption(id: y['id'] as int, name: y['name'] as String, isCurrent: y['is_current'] as bool? ?? false)).toList();
});

final subjectOptionsProvider = FutureProvider.autoDispose<List<SubjectOption>>((ref) async {
  final body = await ref.watch(apiClientProvider).get('/subjects');

  return _rows(body).map((s) => SubjectOption(id: s['id'] as int, name: s['name'] as String)).toList();
});

final guardianOptionsProvider = FutureProvider.autoDispose<List<GuardianModel>>((ref) async {
  final body = await ref.watch(apiClientProvider).get('/guardians', query: {'per_page': 100});

  return _rows(body).map(GuardianModel.fromJson).toList();
});

/// Users holding the Driver role. Needs users.view; without it the driver
/// picker is simply empty rather than failing the whole form.
final driverOptionsProvider = FutureProvider.autoDispose<List<DriverOption>>((ref) async {
  try {
    final body = await ref.watch(apiClientProvider).get('/users', query: {'per_page': 100});

    return _rows(body)
        .where((u) => ((u['roles'] as List<dynamic>?) ?? const []).contains('Driver'))
        .map((u) => DriverOption(id: u['id'] as String, name: u['name'] as String))
        .toList();
  } catch (_) {
    return const [];
  }
});

/// Sends a create/update and returns the response body.
extension ApiWrites on ApiClient {
  Future<Map<String, dynamic>> save(String path, {required bool isUpdate, required Map<String, dynamic> data}) =>
      isUpdate ? put(path, data: data) : post(path, data: data);
}

/// A list endpoint's `data` rows as plain maps (for the simple management screens).
extension ApiRows on ApiClient {
  Future<List<Map<String, dynamic>>> rows(String path, {Map<String, dynamic>? query}) async {
    final body = await get(path, query: query);

    return (body['data'] as List<dynamic>).cast<Map<String, dynamic>>();
  }
}

/// Active students matching a search, as picker choices (value = the student's uuid).
Future<List<FieldChoice>> searchStudents(ApiClient api, String query) async {
  final rows = await api.rows('/students', query: {'search': query, 'status': 'active', 'per_page': 30});

  return [for (final r in rows) FieldChoice(r['id'] as String, '${r['full_name']} · ${r['admission_number']}')];
}

/// Role names a school offers (for onboarding staff). Falls back to the built-in roles
/// when the signed-in user is not allowed to list roles.
final roleNamesProvider = FutureProvider.autoDispose<List<String>>((ref) async {
  try {
    final rows = await ref.watch(apiClientProvider).rows('/roles');
    final names = [for (final r in rows) r['name'] as String]..sort();

    return names.where((n) => n != 'Super Admin').toList();
  } catch (_) {
    return const ['Teacher', 'Accountant', 'HR', 'Librarian', 'Driver', 'Warden', 'Transport Manager', 'Registrar'];
  }
});

/// Users holding a given role (wardens, drivers…), by uuid.
final usersWithRoleProvider = FutureProvider.autoDispose.family<List<DriverOption>, String>((ref, role) async {
  try {
    final rows = await ref.watch(apiClientProvider).rows('/users', query: {'per_page': 100});

    return [
      for (final u in rows)
        if (((u['roles'] as List<dynamic>?) ?? const []).contains(role)) DriverOption(id: u['id'] as String, name: u['name'] as String),
    ];
  } catch (_) {
    return const [];
  }
});
