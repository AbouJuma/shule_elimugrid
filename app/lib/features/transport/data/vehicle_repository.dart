import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/vehicle_model.dart';

class VehicleRepository {
  VehicleRepository(this._client);

  final ApiClient _client;

  Future<PaginatedResult<VehicleModel>> list({String? search, String? status, int page = 1}) async {
    final body = await _client.get(
      '/vehicles',
      query: {
        'page': page,
        if (search != null && search.isNotEmpty) 'search': search,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );

    return PaginatedResult.fromJson(body, VehicleModel.fromJson);
  }

  Future<VehicleModel> show(String id) async {
    final body = await _client.get('/vehicles/$id');
    return VehicleModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<VehicleModel> updateLocation(String id, {required double latitude, required double longitude}) async {
    final body = await _client.post(
      '/vehicles/$id/location',
      data: {'latitude': latitude, 'longitude': longitude},
    );

    return VehicleModel.fromJson(body['data'] as Map<String, dynamic>);
  }
}
