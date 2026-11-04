import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../network/api_exception.dart';
import '../providers.dart';

/// Overridden in main() with the locale read from secure storage before the
/// first frame, so the app never flashes English first for a Swahili user.
final initialLocaleProvider = Provider<Locale>((ref) => const Locale('en'));

final localeProvider = NotifierProvider<LocaleController, Locale>(LocaleController.new);

class LocaleController extends Notifier<Locale> {
  /// True once the user has picked a language in this app session (e.g. on
  /// the login screen). That explicit choice must beat the saved server value.
  bool _pickedThisSession = false;

  @override
  Locale build() => ref.watch(initialLocaleProvider);

  /// User picked a language in the app: remember it on the device and, when
  /// signed in, on the server (so PDFs and API messages follow it too).
  Future<void> choose(String code, {required bool signedIn}) async {
    _pickedThisSession = true;

    if (state.languageCode == code) return;

    state = Locale(code);
    await ref.read(tokenStorageProvider).saveLocale(code);

    if (signedIn) {
      try {
        await ref.read(apiClientProvider).put('/auth/locale', data: {'locale': code});
      } on ApiException {
        // The device preference still applies; the server copy syncs next time.
      }
    }
  }

  /// Right after a successful login (token already set). If the user just
  /// picked a language on the login screen, keep it and save it to their
  /// account; otherwise adopt the language saved on the server.
  Future<void> reconcileAfterLogin(String? serverCode) async {
    if (_pickedThisSession && serverCode != state.languageCode) {
      try {
        await ref.read(apiClientProvider).put('/auth/locale', data: {'locale': state.languageCode});
      } on ApiException {
        // The device preference still applies; the server copy syncs next time.
      }

      return;
    }

    await adoptFromServer(serverCode);
  }

  /// The server knows this user's saved language (e.g. set on the web
  /// portal) — adopt it after login/session restore.
  Future<void> adoptFromServer(String? code) async {
    if (code == null || code == state.languageCode) return;
    if (!const ['en', 'sw'].contains(code)) return;

    state = Locale(code);
    await ref.read(tokenStorageProvider).saveLocale(code);
  }
}
