import '../../../core/network/api_client.dart';
import '../../attendance/domain/attendance_models.dart';
import '../domain/staff_attendance_models.dart';

class StaffAttendanceRepository {
  StaffAttendanceRepository(this._client);

  final ApiClient _client;

  Future<StaffAttendanceRegister> register({required String date}) async {
    final body = await _client.get('/staff-attendance', query: {'date': date});
    return StaffAttendanceRegister.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> markAttendance({required String date, required List<StaffAttendanceEntry> entries}) async {
    await _client.post('/staff-attendance', data: {
      'date': date,
      'records': entries
          .where((e) => e.status != AttendanceStatus.unmarked)
          .map((e) => {'user_id': e.userId, 'status': e.status.toApi()})
          .toList(),
    });
  }

  Future<void> unlock({required String date}) async {
    await _client.post('/staff-attendance/unlock', data: {'date': date});
  }
}
