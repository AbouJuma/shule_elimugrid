import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_spacing.dart';
import '../../auth/presentation/auth_controller.dart';
import 'driver_trip_screen.dart';
import 'live_trips_screen.dart';
import 'routes_list_body.dart';
import 'vehicles_list_screen.dart';
import '../../../core/i18n/app_localizations.dart';

/// The "Transport" tab depends on who is signed in:
///  - a **driver** (can track, cannot manage) lands on their trip screen;
///  - a **transport manager** gets the live map of every running trip plus the fleet;
///  - anyone else with view-only access sees the fleet list.
/// Drivers also hold transport.view, so the driver check must come first —
/// otherwise they would only ever see the whole-school vehicle list.
class TransportTabScreen extends ConsumerWidget {
  const TransportTabScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?.user;
    final canManage = user?.can('transport.manage') ?? false;
    final canTrack = user?.can('transport.track') ?? false;
    final canView = user?.can('transport.view') ?? false;

    if (canTrack && !canManage) return const DriverTripScreen();

    if (canManage) {
      return DefaultTabController(
        length: 3,
        child: Scaffold(
          appBar: AppBar(
            title: Text(context.tr('Transport')),
            bottom: TabBar(tabs: [Tab(text: context.tr('Live trips')), Tab(text: context.tr('Vehicles')), Tab(text: context.tr('Routes'))]),
          ),
          body: const TabBarView(children: [LiveTripsBody(), VehiclesListBody(), RoutesListBody()]),
        ),
      );
    }

    if (canView) return const VehiclesListScreen();

    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Text(context.tr('You do not have access to transport information.')),
        ),
      ),
    );
  }
}
