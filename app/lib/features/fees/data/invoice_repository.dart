import '../../../core/network/api_client.dart';
import '../../../core/network/paginated_result.dart';
import '../domain/invoice_models.dart';

class InvoiceRepository {
  InvoiceRepository(this._client);

  final ApiClient _client;

  Future<PaginatedResult<InvoiceModel>> list({String? status, int page = 1}) async {
    final body = await _client.get(
      '/invoices',
      query: {
        'page': page,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );

    return PaginatedResult.fromJson(body, InvoiceModel.fromJson);
  }

  Future<InvoiceModel> show(String id) async {
    final body = await _client.get('/invoices/$id');
    return InvoiceModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<InvoiceModel> recordPayment(
    String invoiceId, {
    required num amount,
    required String method,
    String? reference,
    String? notes,
    required String paidOn,
  }) async {
    final body = await _client.post('/invoices/$invoiceId/payments', data: {
      'amount': amount,
      'method': method,
      if (reference != null && reference.isNotEmpty) 'reference': reference,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      'paid_on': paidOn,
    });

    return InvoiceModel.fromJson(body['data'] as Map<String, dynamic>);
  }
}
