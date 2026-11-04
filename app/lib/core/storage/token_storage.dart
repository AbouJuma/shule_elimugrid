import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Sanctum bearer token across app restarts. Uses the
/// platform keychain/keystore via flutter_secure_storage — never
/// SharedPreferences, since this is a credential.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';

  Future<void> save(String token) => _storage.write(key: _tokenKey, value: token);

  Future<String?> read() => _storage.read(key: _tokenKey);

  Future<void> clear() => _storage.delete(key: _tokenKey);

  static const _localeKey = 'locale';

  Future<void> saveLocale(String code) => _storage.write(key: _localeKey, value: code);

  Future<String?> readLocale() => _storage.read(key: _localeKey);
}
