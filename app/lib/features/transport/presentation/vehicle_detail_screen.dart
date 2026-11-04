import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/vehicle_model.dart';
import '../../auth/presentation/auth_controller.dart';
import 'vehicle_form_screen.dart';
import 'vehicles_controller.dart';
import '../../../core/config/app_config.dart';
import '../../../core/i18n/app_localizations.dart';

class VehicleDetailScreen extends ConsumerWidget {
  const VehicleDetailScreen({super.key, required this.vehicleId});

  final String vehicleId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncVehicle = ref.watch(vehicleDetailProvider(vehicleId));

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr('Vehicle')),
        actions: [
          if ((ref.watch(authControllerProvider).value?.user?.can('transport.manage') ?? false) && asyncVehicle.hasValue)
            IconButton(
              tooltip: context.tr('Edit'),
              icon: const Icon(Icons.edit_outlined),
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => VehicleFormScreen(vehicle: asyncVehicle.requireValue))),
            ),
        ],
      ),
      body: switch (asyncVehicle) {
        AsyncData(:final value) => _VehicleDetailBody(vehicle: value),
        AsyncError(:final error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load this vehicle.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _VehicleDetailBody extends StatelessWidget {
  const _VehicleDetailBody({required this.vehicle});

  final VehicleModel vehicle;

  @override
  Widget build(BuildContext context) {
    final location = vehicle.lastLocation;

    return ListView(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(vehicle.registrationNumber, style: AppTypography.headingLg),
              const SizedBox(height: AppSpacing.xs),
              Text(
                '${vehicle.make ?? ''} ${vehicle.model ?? ''}'.trim().isEmpty
                    ? context.tr('No make/model on file')
                    : '${vehicle.make ?? ''} ${vehicle.model ?? ''}'.trim(),
                style: AppTypography.bodyMd.copyWith(color: AppColors.textSecondary),
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                '${context.tr('Driver:')} ${vehicle.driver?.name ?? context.tr('Unassigned')}',
                style: AppTypography.bodyMd,
              ),
            ],
          ),
        ),
        if (location == null)
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Center(
              child: Text(
                context.tr('This vehicle has not reported a location yet.'),
                style: AppTypography.bodyMd,
                textAlign: TextAlign.center,
              ),
            ),
          )
        else ...[
          SizedBox(
            height: 320,
            child: FlutterMap(
              options: MapOptions(
                initialCenter: LatLng(location.latitude, location.longitude),
                initialZoom: 15,
              ),
              children: [
                TileLayer(
                  urlTemplate: AppConfig.tileUrlTemplate,
                  userAgentPackageName: 'com.elimugrid.app',
                ),
                MarkerLayer(
                  markers: [
                    Marker(
                      point: LatLng(location.latitude, location.longitude),
                      width: 40,
                      height: 40,
                      child: const Icon(Icons.directions_bus, color: AppColors.gold700, size: 32),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Text(
              '${context.tr('Last updated:')} ${location.recordedAt}',
              style: AppTypography.bodySm,
            ),
          ),
        ],
      ],
    );
  }
}
