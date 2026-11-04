/// Normalized error surfaced to the UI layer. Wraps both network failures
/// and Laravel's validation-error shape ({"message": ..., "errors": {...}}).
class ApiException implements Exception {
  ApiException({required this.message, this.statusCode, this.fieldErrors});

  final String message;
  final int? statusCode;
  final Map<String, List<String>>? fieldErrors;

  String? firstErrorFor(String field) => fieldErrors?[field]?.firstOrNull;

  @override
  String toString() => message;
}
