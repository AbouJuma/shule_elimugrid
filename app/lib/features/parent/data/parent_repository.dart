import '../../../core/network/api_client.dart';
import '../../timetable/domain/timetable_models.dart';
import '../domain/parent_models.dart';

class ParentRepository {
  ParentRepository(this._client);

  final ApiClient _client;

  Future<List<ChildSummary>> children() async {
    final body = await _client.get('/my/children');

    return (body['data'] as List<dynamic>).map((c) => ChildSummary.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<ChildBus> bus(String childId) async => ChildBus.fromJson((await _client.get('/my/children/$childId/bus'))['data'] as Map<String, dynamic>);

  Future<ChildFees> fees(String childId) async => ChildFees.fromJson((await _client.get('/my/children/$childId/fees'))['data'] as Map<String, dynamic>);

  Future<ChildAttendance> attendance(String childId) async =>
      ChildAttendance.fromJson((await _client.get('/my/children/$childId/attendance'))['data'] as Map<String, dynamic>);

  Future<ChildResults> results(String childId, {int? termId}) async =>
      ChildResults.fromJson((await _client.get('/my/children/$childId/results', query: termId != null ? {'term_id': termId} : null))['data'] as Map<String, dynamic>);

  Future<TimetableData> timetable(String childId) async =>
      TimetableData.fromJson((await _client.get('/my/children/$childId/timetable'))['data'] as Map<String, dynamic>);

  Future<void> changePassword({required String current, required String password, required String confirmation}) => _client.put(
    '/auth/password',
    data: {'current_password': current, 'password': password, 'password_confirmation': confirmation},
  );
}
