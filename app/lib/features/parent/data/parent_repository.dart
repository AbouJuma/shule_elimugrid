import '../../../core/network/api_client.dart';
import '../../timetable/domain/timetable_models.dart';
import '../domain/parent_models.dart';

class ParentRepository {
  ParentRepository(this._client);

  final ApiClient _client;

  Future<List<ChildSummary>> children() async {
    final body = await _client.get('/my/children');

    return (body['data'] as List<dynamic>).map((c) => ChildSummary.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<ChildBus> bus(String childId) async => ChildBus.fromJson((await _client.get('/my/children/$childId/bus'))['data'] as Map<String, dynamic>);

  Future<ChildFees> fees(String childId) async => ChildFees.fromJson((await _client.get('/my/children/$childId/fees'))['data'] as Map<String, dynamic>);

  Future<ChildAttendance> attendance(String childId) async =>
      ChildAttendance.fromJson((await _client.get('/my/children/$childId/attendance'))['data'] as Map<String, dynamic>);

  Future<ChildResults> results(String childId, {int? termId}) async =>
      ChildResults.fromJson((await _client.get('/my/children/$childId/results', query: termId != null ? {'term_id': termId} : null))['data'] as Map<String, dynamic>);

  Future<TimetableData> timetable(String childId) async =>
      TimetableData.fromJson((await _client.get('/my/children/$childId/timetable'))['data'] as Map<String, dynamic>);

  Future<void> changePassword({required String current, required String password, required String confirmation}) => _client.put(
    '/auth/password',
    data: {'current_password': current, 'password': password, 'password_confirmation': confirmation},
  );

  /// Mobile money (Airtel/Tigo/Halopesa/Azampesa/Mpesa) pushes a prompt to
  /// [msisdn]; bank (CRDB/NMB) needs [otp] (the guardian dials their bank's
  /// own USSD code to get one) and [accountNumber] as well.
  Future<GatewayTransaction> payInvoice(
    String childId,
    String invoiceId, {
    required String msisdn,
    required String provider,
    String? amount,
    String? otp,
    String? accountNumber,
  }) async {
    final body = await _client.post(
      '/my/children/$childId/invoices/$invoiceId/pay',
      data: {
        'msisdn': msisdn,
        'provider': provider,
        if (amount != null && amount.isNotEmpty) 'amount': amount,
        if (otp != null && otp.isNotEmpty) 'otp': otp,
        if (accountNumber != null && accountNumber.isNotEmpty) 'account_number': accountNumber,
      },
    );

    return GatewayTransaction.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Poll this while waiting for the guardian to approve the push on their phone.
  Future<GatewayTransaction> paymentStatus(String childId, String transactionId) async {
    final body = await _client.get('/my/children/$childId/payments/$transactionId');
    return GatewayTransaction.fromJson(body['data'] as Map<String, dynamic>);
  }
}
