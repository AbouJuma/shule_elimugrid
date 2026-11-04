import '../../../core/network/api_client.dart';
import '../domain/leave_models.dart';

class LeaveRepository {
  LeaveRepository(this._client);

  final ApiClient _client;

  Future<LeaveOverview> overview() async {
    final body = await _client.get('/leave');
    return LeaveOverview.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> apply({
    required String leaveTypeId,
    required String startDate,
    required String endDate,
    String? reason,
  }) => _client.post('/leave', data: {
    'leave_type_id': leaveTypeId,
    'start_date': startDate,
    'end_date': endDate,
    if (reason != null && reason.isNotEmpty) 'reason': reason,
  });

  Future<void> approve(String id) => _client.patch('/leave/$id/approve');

  Future<void> reject(String id) => _client.patch('/leave/$id/reject');
}
