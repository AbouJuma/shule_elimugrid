import 'package:flutter/material.dart';
import 'sw_strings.dart';

/// Lightweight translations (English is the source text, Swahili the only
/// other language). English strings ARE the keys, so an untranslated string
/// simply shows in English instead of a broken key. `{name}` placeholders are
/// filled from [args].
///
/// Widgets call `context.tr('Text')`, which registers a dependency on the
/// [Localizations] widget — so switching language rebuilds every screen
/// automatically, no manual refresh needed.
class AppLocalizations {
  const AppLocalizations(this.locale);

  final Locale locale;

  static const supportedLocales = [Locale('en'), Locale('sw')];

  /// The locale currently shown, for code that has no BuildContext (e.g. the
  /// network layer's fallback error messages). Kept in sync by the delegate.
  static Locale current = const Locale('en');

  /// Translate without a BuildContext, using [current].
  static String translate(String key, [Map<String, String>? args]) => AppLocalizations(current).tr(key, args);

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  static AppLocalizations of(BuildContext context) =>
      Localizations.of<AppLocalizations>(context, AppLocalizations) ?? const AppLocalizations(Locale('en'));

  String tr(String key, [Map<String, String>? args]) {
    var text = locale.languageCode == 'sw' ? (swStrings[key] ?? key) : key;

    args?.forEach((name, value) => text = text.replaceAll('{$name}', value));

    return text;
  }
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => AppLocalizations.supportedLocales.any((l) => l.languageCode == locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async {
    AppLocalizations.current = locale;

    return AppLocalizations(locale);
  }

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

extension TranslateContext on BuildContext {
  String tr(String key, [Map<String, String>? args]) => AppLocalizations.of(this).tr(key, args);
}
