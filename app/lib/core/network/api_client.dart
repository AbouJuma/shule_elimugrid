import 'dart:typed_data';
import '../i18n/app_localizations.dart';
import 'package:dio/dio.dart';
import '../config/app_config.dart';
import 'api_exception.dart';

/// Thin wrapper around Dio: fixed base URL/headers, bearer-token attachment,
/// and translation of Laravel's error responses into ApiException. Laravel
/// remains authoritative for every business decision — this class only
/// moves bytes and normalizes errors, it never contains business logic.
class ApiClient {
  ApiClient()
    : _dio = Dio(
        BaseOptions(
          baseUrl: AppConfig.apiBaseUrl,
          headers: {'Accept': 'application/json'},
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 15),
        ),
      );

  final Dio _dio;

  void setAuthToken(String? token) {
    if (token == null) {
      _dio.options.headers.remove('Authorization');
    } else {
      _dio.options.headers['Authorization'] = 'Bearer $token';
    }
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _request(() => _dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) =>
      _request(() => _dio.post(path, data: data));

  Future<Map<String, dynamic>> patch(String path, {Map<String, dynamic>? data}) =>
      _request(() => _dio.patch(path, data: data));

  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) =>
      _request(() => _dio.put(path, data: data));

  Future<Map<String, dynamic>> delete(String path) => _request(() => _dio.delete(path));

  /// For absolute URLs outside the `/api/v1` base path — e.g. the
  /// `pdf_url` a resource returns, which points at the shared
  /// portal+API PDF route (`auth:sanctum,web`). Dio uses an absolute URL
  /// as-is rather than prefixing it with `baseUrl`, while still
  /// attaching the same Authorization header.
  Future<Uint8List> getBytes(String absoluteUrl) async {
    try {
      final response = await _dio.get<List<int>>(
        absoluteUrl,
        options: Options(responseType: ResponseType.bytes),
      );
      return Uint8List.fromList(response.data ?? const []);
    } on DioException catch (error) {
      throw _toApiException(error);
    }
  }

  Future<Map<String, dynamic>> _request(
    Future<Response<dynamic>> Function() send,
  ) async {
    try {
      final response = await send();
      final body = response.data;
      return body is Map<String, dynamic> ? body : <String, dynamic>{};
    } on DioException catch (error) {
      throw _toApiException(error);
    }
  }

  ApiException _toApiException(DioException error) {
    final response = error.response;
    final data = response?.data;

    if (data is Map<String, dynamic>) {
      final message = data['message'] as String? ?? AppLocalizations.translate('Something went wrong.');
      final rawErrors = data['errors'];
      Map<String, List<String>>? fieldErrors;

      if (rawErrors is Map) {
        fieldErrors = rawErrors.map(
          (key, value) => MapEntry(key.toString(), List<String>.from(value as List)),
        );
      }

      return ApiException(
        message: message,
        statusCode: response?.statusCode,
        fieldErrors: fieldErrors,
      );
    }

    return ApiException(
      message: switch (error.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.receiveTimeout ||
        DioExceptionType.sendTimeout => AppLocalizations.translate('The connection timed out. Check your network and try again.'),
        DioExceptionType.connectionError => AppLocalizations.translate('Could not reach the server. Check your connection.'),
        _ => AppLocalizations.translate('Something went wrong. Please try again.'),
      },
      statusCode: response?.statusCode,
    );
  }
}
