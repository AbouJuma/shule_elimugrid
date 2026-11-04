import '../../../core/network/api_client.dart';
import '../domain/timetable_models.dart';

class TimetableRepository {
  TimetableRepository(this._client);

  final ApiClient _client;

  Future<TimetableData> mine() async {
    final body = await _client.get('/timetable/mine');
    return TimetableData.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<TimetableData> forClass(int classId) async {
    final body = await _client.get('/timetable/classes/$classId');
    return TimetableData.fromJson(body['data'] as Map<String, dynamic>);
  }
}
