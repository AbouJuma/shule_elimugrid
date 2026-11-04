import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/guardian_model.dart';

class GuardianRepository {
  GuardianRepository(this._client);

  final ApiClient _client;

  Future<PaginatedResult<GuardianModel>> list({String? search, int page = 1}) async {
    final body = await _client.get(
      '/guardians',
      query: {
        'page': page,
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );

    return PaginatedResult.fromJson(body, GuardianModel.fromJson);
  }
}
