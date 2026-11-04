import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/vehicle_model.dart';
import '../../auth/presentation/auth_controller.dart';
import 'vehicle_form_screen.dart';
import 'vehicles_controller.dart';
import '../../../core/i18n/app_localizations.dart';

class VehiclesListScreen extends StatelessWidget {
  const VehiclesListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Vehicles'))),
      body: const VehiclesListBody(),
    );
  }
}

/// The vehicle list without its own Scaffold, so it can sit inside a tab.
class VehiclesListBody extends ConsumerWidget {
  const VehiclesListBody({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncVehicles = ref.watch(vehiclesListProvider);
    final canManage = ref.watch(authControllerProvider).value?.user?.can('transport.manage') ?? false;

    return Stack(children: [
      RefreshIndicator(
        onRefresh: () => ref.refresh(vehiclesListProvider.future),
        child: switch (asyncVehicles) {
          AsyncData(:final value) when value.isEmpty => ListView(
            children: [
              Padding(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Center(child: Text(context.tr('No vehicles found.'), style: AppTypography.bodyMd)),
              ),
            ],
          ),
          AsyncData(:final value) => ListView.separated(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
            itemCount: value.length,
            separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
            itemBuilder: (context, index) => _VehicleTile(vehicle: value[index]),
          ),
          AsyncError(:final error) => ListView(
            children: [
              Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Text('${context.tr('Could not load vehicles.')}\n$error', textAlign: TextAlign.center),
              ),
            ],
          ),
          _ => const Center(child: CircularProgressIndicator()),
        },
      ),
      if (canManage)
        Positioned(
          right: AppSpacing.md,
          bottom: AppSpacing.md,
          child: FloatingActionButton.extended(
            heroTag: 'add-vehicle',
            onPressed: () async {
              final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const VehicleFormScreen()));
              if (saved == true) ref.invalidate(vehiclesListProvider);
            },
            icon: const Icon(Icons.add),
            label: Text(context.tr('Add vehicle')),
          ),
        ),
    ]);
  }
}

class _VehicleTile extends StatelessWidget {
  const _VehicleTile({required this.vehicle});

  final VehicleModel vehicle;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: () => context.push('/home/transport/vehicles/${vehicle.id}'),
      leading: CircleAvatar(
        backgroundColor: AppColors.navy100,
        child: const Icon(Icons.directions_bus, color: AppColors.navy700),
      ),
      title: Text(vehicle.registrationNumber, style: AppTypography.bodyLg),
      subtitle: Text(
        [
          if (vehicle.make != null || vehicle.model != null) '${vehicle.make ?? ''} ${vehicle.model ?? ''}'.trim(),
          vehicle.driver?.name ?? context.tr('No driver assigned'),
        ].join(' · '),
        style: AppTypography.bodySm,
      ),
      trailing: Icon(
        vehicle.lastLocation != null ? Icons.location_on : Icons.location_off,
        color: vehicle.lastLocation != null ? AppColors.green600 : AppColors.textSecondary,
        size: 20,
      ),
    );
  }
}
