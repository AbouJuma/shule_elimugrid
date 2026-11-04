import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/i18n/app_localizations.dart';
import '../../features/transport/presentation/trip_tracker.dart';

/// Bottom-nav shell. Deliberately just two stable, role-agnostic
/// destinations — Home and Profile. Every module (Students, Transport,
/// and whatever ships next) lives as a tile on the Home screen instead of
/// its own nav-bar slot: a bottom nav that grows a new icon per module
/// stops being navigable well before a school's full feature set fits,
/// and half those icons would be irrelevant to any given role anyway.
class HomeShell extends ConsumerWidget {
  const HomeShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // A driver's trip tracking must outlive whichever screen is open, so it is
    // held here for the whole signed-in session (it is a no-op for other roles).
    ref.watch(tripTrackerProvider);

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: (index) =>
            navigationShell.goBranch(index, initialLocation: index == navigationShell.currentIndex),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home), label: context.tr('Home')),
          NavigationDestination(icon: const Icon(Icons.person_outline), selectedIcon: const Icon(Icons.person), label: context.tr('Profile')),
        ],
      ),
    );
  }
}
