import '../../../core/network/api_client.dart';
import '../domain/attendance_models.dart';

class AttendanceRepository {
  AttendanceRepository(this._client);

  final ApiClient _client;

  Future<List<AttendanceClass>> classes() async {
    final body = await _client.get('/attendance/classes');
    return (body['data'] as List<dynamic>)
        .map((c) => AttendanceClass.fromJson(c as Map<String, dynamic>))
        .toList();
  }

  Future<AttendanceRegister> register({required int classId, required String date, int? subjectId}) async {
    final body = await _client.get(
      '/attendance',
      query: {'school_class_id': classId, 'date': date, 'subject_id': ?subjectId},
    );
    return AttendanceRegister.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> markAttendance({
    required int classId,
    required String date,
    int? subjectId,
    required List<RegisterEntry> entries,
  }) async {
    await _client.post('/attendance', data: {
      'school_class_id': classId,
      'date': date,
      'subject_id': ?subjectId,
      'records': entries
          .where((e) => e.status != AttendanceStatus.unmarked)
          .map((e) => {'student_id': e.studentId, 'status': e.status.toApi()})
          .toList(),
    });
  }

  Future<void> unlock({required int classId, required String date, int? subjectId}) async {
    await _client.post('/attendance/unlock', data: {
      'school_class_id': classId,
      'date': date,
      'subject_id': ?subjectId,
    });
  }
}
