import 'dart:typed_data';
import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/certificate_model.dart';

class CertificateRepository {
  CertificateRepository(this._client);

  final ApiClient _client;

  Future<PaginatedResult<CertificateModel>> list({int page = 1}) async {
    final body = await _client.get('/certificates', query: {'page': page});
    return PaginatedResult.fromJson(body, CertificateModel.fromJson);
  }

  Future<CertificateModel> show(int id) async {
    final body = await _client.get('/certificates/$id');
    return CertificateModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Uint8List> downloadPdf(String pdfUrl) => _client.getBytes(pdfUrl);
}
