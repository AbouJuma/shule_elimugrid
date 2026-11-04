import '../../../core/network/api_client.dart';

class ScanResult {
  const ScanResult({required this.type, required this.name, required this.subLabel, required this.status, required this.alreadyMarked});

  final String type; // 'student' | 'staff'
  final String name;
  final String? subLabel;
  final String status;
  final bool alreadyMarked;

  factory ScanResult.fromJson(Map<String, dynamic> json) => ScanResult(
    type: json['type'] as String,
    name: json['name'] as String,
    subLabel: json['sub_label'] as String?,
    status: json['status'] as String,
    alreadyMarked: json['already_marked'] as bool? ?? false,
  );
}

class AttendanceScanRepository {
  AttendanceScanRepository(this._client);

  final ApiClient _client;

  Future<ScanResult> scan(String code) async {
    final body = await _client.post('/attendance/scan', data: {'code': code});
    return ScanResult.fromJson(body['data'] as Map<String, dynamic>);
  }
}
