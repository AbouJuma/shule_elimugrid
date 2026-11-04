import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/student_model.dart';

class StudentRepository {
  StudentRepository(this._client);

  final ApiClient _client;

  Future<PaginatedResult<StudentModel>> list({
    String? search,
    String? status,
    int? classId,
    int page = 1,
  }) async {
    final body = await _client.get(
      '/students',
      query: {
        'page': page,
        if (search != null && search.isNotEmpty) 'search': search,
        if (status != null && status.isNotEmpty) 'status': status,
        'school_class_id': ?classId,
      },
    );

    return PaginatedResult.fromJson(body, StudentModel.fromJson);
  }

  Future<StudentModel> show(String id) async {
    final body = await _client.get('/students/$id');
    return StudentModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Status changes must carry a reason (the API rejects them otherwise) and
  /// are logged server-side with who made them.
  Future<StudentModel> changeStatus(String id, {required String status, required String reason}) async {
    final body = await _client.put('/students/$id', data: {'status': status, 'reason': reason});
    return StudentModel.fromJson(body['data'] as Map<String, dynamic>);
  }
}
