import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../domain/trip_models.dart';
import '../domain/vehicle_model.dart';
import 'my_vehicle_controller.dart';
import 'trip_providers.dart';
import 'trip_tracker.dart';
import 'widgets/trip_map.dart';
import 'widgets/trip_widgets.dart';

String _errorText(BuildContext context, Object e) {
  if (e is ApiException) {
    final field = e.fieldErrors?.values.firstOrNull?.firstOrNull;

    return field ?? e.message;
  }

  return e.toString();
}

/// The driver's home: pick a route and start the trip, then run it —
/// live map, next stop, "arrived", and end.
class DriverTripScreen extends ConsumerWidget {
  const DriverTripScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final trip = ref.watch(driverTripProvider);
    final vehicle = ref.watch(myVehicleProvider);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My trip'))),
      body: switch ((trip, vehicle)) {
        (AsyncData(value: final t?), _) when t.isActive => _RunningTrip(trip: t),
        (AsyncData(), AsyncData(value: null)) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text(
              context.tr('No vehicle is currently assigned to you. Contact your school administrator.'),
              textAlign: TextAlign.center,
              style: AppTypography.bodyMd,
            ),
          ),
        ),
        (AsyncData(), AsyncData(value: final v?)) => _StartTrip(vehicle: v),
        (AsyncError(:final error), _) || (_, AsyncError(:final error)) => Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text('${context.tr('Could not load your vehicle.')}\n$error', textAlign: TextAlign.center),
          ),
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

// ---------------------------------------------------------------------------------------------
// Before the trip: choose the route and direction.
// ---------------------------------------------------------------------------------------------

class _StartTrip extends ConsumerStatefulWidget {
  const _StartTrip({required this.vehicle});

  final VehicleModel vehicle;

  @override
  ConsumerState<_StartTrip> createState() => _StartTripState();
}

class _StartTripState extends ConsumerState<_StartTrip> {
  String? _routeId;
  late String _direction = DateTime.now().hour < 12 ? 'morning' : 'afternoon';
  bool _busy = false;
  String? _error;

  Future<void> _start(TransportRouteModel route) async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final trip = await ref.read(tripRepositoryProvider).start(vehicleId: widget.vehicle.id, routeId: route.id, direction: _direction);
      await ref.read(tripTrackerProvider.notifier).begin(trip.id);
      ref.invalidate(driverTripProvider);
    } catch (e) {
      if (mounted) setState(() => _error = _errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final routes = ref.watch(routesProvider);

    return switch (routes) {
      AsyncData(:final value) => _content(context, value.where((r) => r.status == 'active' && r.stops.isNotEmpty && (r.vehicleId == null || r.vehicleId == widget.vehicle.id)).toList()),
      AsyncError(:final error) => Center(child: Text('$error', textAlign: TextAlign.center)),
      _ => const Center(child: CircularProgressIndicator()),
    };
  }

  Widget _content(BuildContext context, List<TransportRouteModel> routes) {
    final v = widget.vehicle;

    if (routes.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Text(
            context.tr('No route with stops is assigned to your vehicle yet. Ask the transport office to set one up.'),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    final route = routes.firstWhere((r) => r.id == _routeId, orElse: () => routes.first);
    final ordered = [...route.stops]..sort((a, b) => a.sequence.compareTo(b.sequence));
    final visiting = _direction == 'afternoon' ? ordered.reversed.toList() : ordered;
    final previewStops = [
      for (final s in visiting)
        TripStop(id: s.id, name: s.name, sequence: s.sequence, latitude: s.latitude, longitude: s.longitude, pickupTime: s.pickupTime, dropoffTime: s.dropoffTime),
    ];

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${v.registrationNumber}${v.make != null ? ' · ${v.make}' : ''}', style: AppTypography.headingMd),
              const SizedBox(height: AppSpacing.md),
              DropdownButtonFormField<String>(
                key: ValueKey(route.id),
                initialValue: route.id,
                isExpanded: true,
                decoration: InputDecoration(labelText: context.tr('Route'), isDense: true),
                items: routes.map((r) => DropdownMenuItem(value: r.id, child: Text('${r.name} (${r.stops.length} ${context.tr('stops')})'))).toList(),
                onChanged: (id) => setState(() => _routeId = id),
              ),
              const SizedBox(height: AppSpacing.sm),
              SizedBox(
                width: double.infinity,
                child: SegmentedButton<String>(
                  segments: [
                    ButtonSegment(value: 'morning', icon: const Icon(Icons.wb_sunny_outlined), label: Text(context.tr('Morning pick-up'))),
                    ButtonSegment(value: 'afternoon', icon: const Icon(Icons.nights_stay_outlined), label: Text(context.tr('Afternoon drop-off'))),
                  ],
                  selected: {_direction},
                  onSelectionChanged: (s) => setState(() => _direction = s.first),
                ),
              ),
            ],
          ),
        ),
        Expanded(child: TripMap(stops: previewStops, follow: false)),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Text(_error!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
                  ),
                FilledButton.icon(
                  onPressed: _busy ? null : () => _start(route),
                  icon: _busy ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.play_arrow_rounded),
                  label: Text(context.tr('Start trip')),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------------------------
// During the trip.
// ---------------------------------------------------------------------------------------------

class _RunningTrip extends ConsumerStatefulWidget {
  const _RunningTrip({required this.trip});

  final Trip trip;

  @override
  ConsumerState<_RunningTrip> createState() => _RunningTripState();
}

class _RunningTripState extends ConsumerState<_RunningTrip> {
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();

    // Opening the app onto a running trip (or starting one) must be tracking.
    Future.microtask(() {
      if (!ref.read(tripTrackerProvider).isTracking) ref.read(tripTrackerProvider.notifier).begin(widget.trip.id);
    });
  }

  Future<void> _arrive(Trip trip, TripStop stop) async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(tripRepositoryProvider).arrive(trip.id, stop.id);
      ref.invalidate(tripDetailProvider(trip.id));
    } catch (e) {
      if (mounted) setState(() => _error = _errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _end(Trip trip) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('End this trip?')),
        content: Text(trip.visited < trip.total
            ? context.tr('{n} stops have not been reached yet.', {'n': '${trip.total - trip.visited}'})
            : context.tr('All stops have been reached.')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(context.tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(context.tr('End trip'))),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _busy = true);

    try {
      await ref.read(tripRepositoryProvider).end(trip.id);
      ref.read(tripTrackerProvider.notifier).end();
      ref.invalidate(driverTripProvider);
    } catch (e) {
      if (mounted) setState(() => _error = _errorText(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    // The polled detail carries the trail, arrivals and our last reported position.
    final live = ref.watch(tripDetailProvider(widget.trip.id)).value ?? widget.trip;
    final tracker = ref.watch(tripTrackerProvider);
    final next = live.nextStop;

    return Column(
      children: [
        Container(
          color: AppColors.navy800,
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(live.routeName, style: AppTypography.headingSm.copyWith(color: AppColors.white)),
                    Text(
                      '${directionLabel(context, live)} · ${live.registrationNumber} · ${context.tr('since')} ${formatClock(live.startedAt)}',
                      style: AppTypography.bodySm.copyWith(color: AppColors.textOnDark),
                    ),
                  ],
                ),
              ),
              Text('${live.visited}/${live.total}', style: AppTypography.headingMd.copyWith(color: AppColors.gold500)),
            ],
          ),
        ),
        LinearProgressIndicator(value: live.progress, minHeight: 4, color: AppColors.green600, backgroundColor: AppColors.navy100),
        Expanded(child: TripMap(stops: live.stops, trail: live.trail, position: live.position)),
        Material(
          elevation: 8,
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Icon(
                        tracker.error != null ? Icons.gps_off : Icons.gps_fixed,
                        size: 16,
                        color: tracker.error != null ? AppColors.error600 : AppColors.green700,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          tracker.error != null
                              ? context.tr(tracker.error!)
                              : (tracker.lastSentAt == null
                                  ? context.tr('Waiting for a GPS fix…')
                                  : '${context.tr('Location shared')} ${agoShort(tracker.lastSentAt!)} ${context.tr('ago')}'),
                          style: AppTypography.bodySm.copyWith(color: tracker.error != null ? AppColors.error600 : AppColors.textSecondary),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  if (next != null) ...[
                    Text(context.tr('Next stop'), style: AppTypography.label.copyWith(color: AppColors.gold700)),
                    Text(next.name, style: AppTypography.headingSm),
                    if (live.next != null) Text(etaLabel(context, live.next!), style: AppTypography.bodyMd.copyWith(color: AppColors.gold700)),
                    if (next.students > 0) Text(studentCount(context, next.students), style: AppTypography.bodySm),
                  ] else
                    Text(context.tr('All stops have been reached.'), style: AppTypography.headingSm.copyWith(color: AppColors.green700)),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.xs),
                      child: Text(_error!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
                    ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: [
                      if (next != null)
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: _busy ? null : () => _arrive(live, next),
                            icon: const Icon(Icons.flag_rounded),
                            label: Text(context.tr('Arrived')),
                          ),
                        ),
                      if (next != null) const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _busy ? null : () => _end(live),
                          style: OutlinedButton.styleFrom(foregroundColor: AppColors.error600),
                          icon: const Icon(Icons.stop_circle_outlined),
                          label: Text(context.tr('End trip')),
                        ),
                      ),
                    ],
                  ),
                  ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    title: Text('${context.tr('Stops')} (${live.visited}/${live.total})', style: AppTypography.bodyMd),
                    children: [SizedBox(height: 200, child: TripStopsList(trip: live))],
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
