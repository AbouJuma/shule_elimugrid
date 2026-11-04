import '../../../core/network/api_client.dart';
import '../domain/user_model.dart';

class LoginResult {
  const LoginResult({required this.user, required this.token});

  final UserModel user;
  final String token;
}

class AuthRepository {
  AuthRepository(this._client);

  final ApiClient _client;

  Future<LoginResult> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final body = await _client.post(
      '/auth/login',
      data: {'email': email, 'password': password, 'device_name': deviceName},
    );

    final data = body['data'] as Map<String, dynamic>;
    return LoginResult(
      user: UserModel.fromJson(data['user'] as Map<String, dynamic>),
      token: data['token'] as String,
    );
  }

  Future<UserModel> me() async {
    final body = await _client.get('/auth/me');
    return UserModel.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> logout() => _client.post('/auth/logout');
}
