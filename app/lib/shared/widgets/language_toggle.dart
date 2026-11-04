import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/i18n/locale_controller.dart';
import '../../features/auth/presentation/auth_controller.dart';

/// English / Kiswahili switch. Used on the login screen (before anyone is
/// signed in) and in the profile.
class LanguageToggle extends ConsumerWidget {
  const LanguageToggle({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(localeProvider);
    final signedIn = ref.watch(authControllerProvider).value?.isAuthenticated ?? false;

    return SegmentedButton<String>(
      showSelectedIcon: false,
      segments: const [
        ButtonSegment(value: 'en', label: Text('English')),
        ButtonSegment(value: 'sw', label: Text('Kiswahili')),
      ],
      selected: {locale.languageCode},
      onSelectionChanged: (selection) =>
          ref.read(localeProvider.notifier).choose(selection.first, signedIn: signedIn),
    );
  }
}
