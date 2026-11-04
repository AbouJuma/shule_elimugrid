import 'dart:typed_data';
import '../../../core/network/api_client.dart';
import '../domain/payslip_models.dart';

class PayslipRepository {
  PayslipRepository(this._client);

  final ApiClient _client;

  Future<List<Payslip>> mine() async {
    final body = await _client.get('/my/payslips');
    return (body['data'] as List<dynamic>).map((p) => Payslip.fromJson(p as Map<String, dynamic>)).toList();
  }

  Future<Payslip> show(String id) async {
    final body = await _client.get('/my/payslips/$id');
    return Payslip.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Uint8List> downloadPdf(String pdfUrl) => _client.getBytes(pdfUrl);
}
