import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/platform_models.dart';

class PlatformRepository {
  PlatformRepository(this._client);

  final ApiClient _client;

  Future<PlatformDashboard> dashboard() async =>
      PlatformDashboard.fromJson((await _client.get('/super-admin/dashboard'))['data'] as Map<String, dynamic>);

  Future<PaginatedResult<SchoolSummary>> schools({String? search, String? status, int page = 1}) async {
    final body = await _client.get('/super-admin/schools', query: {
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
      if (status != null && status.isNotEmpty) 'status': status,
    });

    return PaginatedResult.fromJson(body, SchoolSummary.fromJson);
  }

  Future<void> suspend(String schoolId) => _client.patch('/super-admin/schools/$schoolId/suspend');

  Future<void> activate(String schoolId) => _client.patch('/super-admin/schools/$schoolId/activate');
}
