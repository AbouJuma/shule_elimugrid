import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/trip_models.dart';
import 'trip_providers.dart';
import 'widgets/trip_map.dart';
import 'widgets/trip_widgets.dart';

/// For transport managers: every bus that is on a trip right now.
class LiveTripsBody extends ConsumerWidget {
  const LiveTripsBody({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return switch (ref.watch(activeTripsProvider)) {
      AsyncData(:final value) when value.isEmpty => Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.directions_bus_outlined, size: 48, color: AppColors.textSecondary),
              const SizedBox(height: AppSpacing.sm),
              Text(context.tr('No trip is running right now.'), style: AppTypography.bodyMd),
              const SizedBox(height: AppSpacing.xs),
              Text(context.tr('A trip appears here as soon as a driver starts it.'), style: AppTypography.bodySm, textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
      AsyncData(:final value) => ListView.separated(
        padding: const EdgeInsets.all(AppSpacing.md),
        itemCount: value.length,
        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
        itemBuilder: (context, i) => _TripCard(trip: value[i]),
      ),
      AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }
}

class _TripCard extends StatelessWidget {
  const _TripCard({required this.trip});

  final Trip trip;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TripWatchScreen(tripId: trip.id))),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.directions_bus_rounded, color: AppColors.gold700),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: Text(trip.registrationNumber, style: AppTypography.headingSm)),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                    decoration: BoxDecoration(color: AppColors.green100, borderRadius: BorderRadius.circular(AppSpacing.radiusPill)),
                    child: Text(context.tr('Live'), style: AppTypography.label.copyWith(color: AppColors.green700)),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.xs),
              Text('${trip.routeName} · ${directionLabel(context, trip)}', style: AppTypography.bodyMd),
              if (trip.driverName != null) Text(trip.driverName!, style: AppTypography.bodySm),
              const SizedBox(height: AppSpacing.xs),
              Text('${context.tr('since')} ${formatClock(trip.startedAt)}', style: AppTypography.bodySm),
            ],
          ),
        ),
      ),
    );
  }
}

/// One trip on a live map, refreshed every few seconds, with the stop list beneath.
class TripWatchScreen extends ConsumerWidget {
  const TripWatchScreen({super.key, required this.tripId});

  final String tripId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final asyncTrip = ref.watch(tripDetailProvider(tripId));

    return Scaffold(
      appBar: AppBar(title: Text(asyncTrip.value?.registrationNumber ?? context.tr('Trip'))),
      body: switch (asyncTrip) {
        AsyncData(:final value) => _Body(trip: value),
        AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.trip});

  final Trip trip;

  @override
  Widget build(BuildContext context) {
    final seen = trip.position?.recordedAt;
    final stale = seen != null && trip.isActive && DateTime.now().difference(seen).inSeconds > 120;
    final next = trip.nextStop;

    return Column(
      children: [
        Container(
          width: double.infinity,
          color: trip.isActive ? AppColors.navy800 : AppColors.charcoal,
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${trip.routeName} · ${directionLabel(context, trip)}', style: AppTypography.headingSm.copyWith(color: AppColors.white)),
              Text(
                [
                  if (trip.driverName != null) trip.driverName!,
                  '${trip.visited}/${trip.total} ${context.tr('stops')}',
                  if (next != null) '${context.tr('Next stop')}: ${next.name}${trip.next != null ? ' (${etaLabel(context, trip.next!)})' : ''}',
                ].join(' · '),
                style: AppTypography.bodySm.copyWith(color: AppColors.textOnDark),
              ),
              Text(
                !trip.isActive
                    ? '${context.tr('Trip completed')} ${trip.endedAt != null ? formatClock(trip.endedAt!) : ''}'
                    : (seen == null
                        ? context.tr('No position received yet')
                        : '${context.tr('Last seen')} ${agoShort(seen)} ${context.tr('ago')}${stale ? ' — ${context.tr('No update for a while')}' : ''}'),
                style: AppTypography.bodySm.copyWith(color: stale ? AppColors.gold500 : AppColors.textOnDark),
              ),
            ],
          ),
        ),
        LinearProgressIndicator(value: trip.progress, minHeight: 4, color: AppColors.green600, backgroundColor: AppColors.navy100),
        Expanded(flex: 5, child: TripMap(stops: trip.stops, trail: trip.trail, position: trip.position)),
        Expanded(flex: 4, child: TripStopsList(trip: trip)),
      ],
    );
  }
}
