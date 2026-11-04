import 'dart:typed_data';
import '../../../core/network/api_client.dart';
import '../domain/results_models.dart';

class ResultsRepository {
  ResultsRepository(this._client);

  final ApiClient _client;

  Future<List<ResultTerm>> terms() async {
    final body = await _client.get('/results/terms');
    return (body['data'] as List<dynamic>).map((t) => ResultTerm.fromJson(t as Map<String, dynamic>)).toList();
  }

  Future<ClassResults> forClass({required int termId, required int classId}) async {
    final body = await _client.get('/results', query: {'term_id': termId, 'school_class_id': classId});
    return ClassResults.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Uint8List> downloadPdf(String pdfUrl) => _client.getBytes(pdfUrl);
}
