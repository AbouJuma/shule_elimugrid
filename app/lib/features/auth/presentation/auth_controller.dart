import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/locale_controller.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../data/auth_repository.dart';
import '../domain/user_model.dart';

enum AuthStatus { authenticated, unauthenticated }

class AuthState {
  const AuthState({required this.status, this.user});

  final AuthStatus status;
  final UserModel? user;

  bool get isAuthenticated => status == AuthStatus.authenticated;
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(apiClientProvider)),
);

final authControllerProvider = AsyncNotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

/// Owns the session lifecycle: on app start, tries to restore a persisted
/// token and validate it against /auth/me; on login/logout, persists or
/// clears the token and updates ApiClient's default header accordingly.
/// The UI only ever reacts to AuthState — it never touches storage or the
/// API client directly.
class AuthController extends AsyncNotifier<AuthState> {
  @override
  Future<AuthState> build() async {
    final tokenStorage = ref.read(tokenStorageProvider);
    final apiClient = ref.read(apiClientProvider);

    final token = await tokenStorage.read();
    if (token == null) {
      return const AuthState(status: AuthStatus.unauthenticated);
    }

    apiClient.setAuthToken(token);

    try {
      final user = await ref.read(authRepositoryProvider).me();
      await ref.read(localeProvider.notifier).adoptFromServer(user.locale);
      return AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException {
      // Stored token is stale/invalid/expired — clear it and fall back to
      // the login screen rather than surfacing an error on cold start.
      await tokenStorage.clear();
      apiClient.setAuthToken(null);
      return const AuthState(status: AuthStatus.unauthenticated);
    }
  }

  /// Throws ApiException on bad credentials. Deliberately does NOT push the
  /// controller into AsyncLoading: the router treats "loading" as the cold-start
  /// splash, which would tear down the login screen mid-request and lose its
  /// error message. The login screen tracks its own busy state instead.
  Future<void> login({required String email, required String password}) async {
    final result = await ref.read(authRepositoryProvider).login(
      email: email,
      password: password,
      deviceName: 'elimugrid-app',
    );

    await ref.read(tokenStorageProvider).save(result.token);
    ref.read(apiClientProvider).setAuthToken(result.token);
    await ref.read(localeProvider.notifier).reconcileAfterLogin(result.user.locale);

    state = AsyncData(AuthState(status: AuthStatus.authenticated, user: result.user));
  }

  Future<void> logout() async {
    final apiClient = ref.read(apiClientProvider);
    final tokenStorage = ref.read(tokenStorageProvider);

    try {
      await ref.read(authRepositoryProvider).logout();
    } on ApiException {
      // Token may already be invalid server-side — proceed with local
      // logout regardless so the user is never stuck.
    }

    await tokenStorage.clear();
    apiClient.setAuthToken(null);
    state = const AsyncData(AuthState(status: AuthStatus.unauthenticated));
  }
}
