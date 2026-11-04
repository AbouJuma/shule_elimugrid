import 'dart:typed_data';
import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/id_card_model.dart';

class IdCardRepository {
  IdCardRepository(this._client);

  final ApiClient _client;

  Future<PaginatedResult<IdCardModel>> list({int page = 1}) async {
    final body = await _client.get('/id-cards', query: {'page': page});
    return PaginatedResult.fromJson(body, IdCardModel.fromJson);
  }

  Future<IdCardModel> show(int id) async {
    final body = await _client.get('/id-cards/$id');
    return IdCardModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Uint8List> downloadPdf(String pdfUrl) => _client.getBytes(pdfUrl);
}
