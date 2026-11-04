import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/forms/entity_form.dart';
import '../domain/trip_models.dart';
import '../domain/vehicle_model.dart';
import 'stop_picker_screen.dart';
import 'trip_providers.dart';
import 'vehicles_controller.dart';

/// Add or edit a route: name, bus, and an ordered list of stops placed on the map.
class RouteFormScreen extends ConsumerStatefulWidget {
  const RouteFormScreen({super.key, this.route});

  final TransportRouteModel? route;

  @override
  ConsumerState<RouteFormScreen> createState() => _RouteFormScreenState();
}

class _RouteFormScreenState extends ConsumerState<RouteFormScreen> {
  late final List<StopDraft> _stops = [
    for (final s in ([...?widget.route?.stops]..sort((a, b) => a.sequence.compareTo(b.sequence))))
      StopDraft(id: s.id, name: s.name, point: s.point, pickupTime: s.pickupTime?.substring(0, 5)),
  ];

  List<LatLng> get _points => _stops.map((s) => s.point).whereType<LatLng>().toList();

  Future<void> _addOrEdit([int? index]) async {
    final result = await Navigator.of(context).push<StopDraft>(
      MaterialPageRoute(builder: (_) => StopPickerScreen(initial: index == null ? null : _stops[index], near: _points)),
    );

    if (result == null) return;
    setState(() => index == null ? _stops.add(result) : _stops[index] = result);
  }

  void _move(int i, int delta) {
    final j = i + delta;
    if (j < 0 || j >= _stops.length) return;

    setState(() {
      final s = _stops.removeAt(i);
      _stops.insert(j, s);
    });
  }

  Future<void> _delete(BuildContext context, TransportRouteModel r) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('Delete this route?')),
        content: Text(r.name),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(context.tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(context.tr('Delete'))),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;

    try {
      await ref.read(apiClientProvider).delete('/transport-routes/${r.id}');
      ref.invalidate(routesProvider);
      if (context.mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e is ApiException ? (e.fieldErrors?.values.firstOrNull?.firstOrNull ?? e.message) : '$e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final vehicles = ref.watch(vehiclesListProvider);
    final r = widget.route;

    if (!vehicles.hasValue) {
      return Scaffold(appBar: AppBar(title: Text(context.tr('Route'))), body: const Center(child: CircularProgressIndicator()));
    }

    return EntityFormScreen(
      title: r == null ? 'Add route' : 'Edit route',
      actions: [if (r != null) IconButton(tooltip: context.tr('Delete'), icon: const Icon(Icons.delete_outline), onPressed: () => _delete(context, r))],
      fields: [
        FieldSpec(key: 'name', label: 'Route name', required: true, initial: r?.name, maxLength: 150),
        FieldSpec(
          key: 'vehicle_id',
          label: 'Bus',
          kind: FieldKind.choice,
          initial: r?.vehicleId,
          choices: [for (final VehicleModel v in vehicles.requireValue) FieldChoice(v.id, '${v.registrationNumber}${v.make != null ? ' · ${v.make}' : ''}')],
        ),
        FieldSpec(
          key: 'status',
          label: 'Status',
          kind: FieldKind.choice,
          required: true,
          initial: r?.status ?? 'active',
          choices: [FieldChoice('active', context.tr('Active')), FieldChoice('inactive', context.tr('Inactive'))],
        ),
      ],
      footer: _StopsEditor(stops: _stops, onAdd: () => _addOrEdit(), onEdit: _addOrEdit, onMove: _move, onRemove: (i) => setState(() => _stops.removeAt(i))),
      onSubmit: (v) async {
        if (_stops.isEmpty) {
          throw ApiException(message: context.tr('Add at least one stop.'));
        }

        final data = {
          'name': v['name'],
          'vehicle_id': (v['vehicle_id'] ?? '').isEmpty ? null : v['vehicle_id'],
          'status': v['status'],
          'stops': [
            for (var i = 0; i < _stops.length; i++)
              {
                if (_stops[i].id != null) 'id': _stops[i].id,
                'name': _stops[i].name,
                'sequence': i + 1,
                'latitude': _stops[i].point?.latitude,
                'longitude': _stops[i].point?.longitude,
                if (_stops[i].pickupTime != null) 'pickup_time': _stops[i].pickupTime,
                if (_stops[i].pickupTime != null) 'dropoff_time': _stops[i].pickupTime,
              },
          ],
        };
        final api = ref.read(apiClientProvider);

        if (r == null) {
          await api.post('/transport-routes', data: data);
        } else {
          await api.put('/transport-routes/${r.id}', data: data);
        }

        ref.invalidate(routesProvider);
      },
    );
  }
}

class _StopsEditor extends StatelessWidget {
  const _StopsEditor({required this.stops, required this.onAdd, required this.onEdit, required this.onMove, required this.onRemove});

  final List<StopDraft> stops;
  final VoidCallback onAdd;
  final void Function(int index) onEdit;
  final void Function(int index, int delta) onMove;
  final void Function(int index) onRemove;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(context.tr('Stops (in the order the bus visits them)'), style: AppTypography.headingSm),
        const SizedBox(height: AppSpacing.sm),
        if (stops.isEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: Text(context.tr('No stops yet — add the first pick-up point.'), style: AppTypography.bodySm),
          ),
        for (var i = 0; i < stops.length; i++)
          Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: ListTile(
              onTap: () => onEdit(i),
              leading: CircleAvatar(radius: 14, backgroundColor: AppColors.navy700, child: Text('${i + 1}', style: const TextStyle(color: Colors.white, fontSize: 12))),
              title: Text(stops[i].name),
              subtitle: Text(
                [
                  if (stops[i].pickupTime != null) stops[i].pickupTime!,
                  if (stops[i].point != null) '${stops[i].point!.latitude.toStringAsFixed(4)}, ${stops[i].point!.longitude.toStringAsFixed(4)}',
                ].join(' · '),
                style: AppTypography.bodySm,
              ),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.arrow_upward, size: 18), onPressed: i == 0 ? null : () => onMove(i, -1)),
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.arrow_downward, size: 18), onPressed: i == stops.length - 1 ? null : () => onMove(i, 1)),
                  IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.close, size: 18, color: AppColors.error600), onPressed: () => onRemove(i)),
                ],
              ),
            ),
          ),
        OutlinedButton.icon(onPressed: onAdd, icon: const Icon(Icons.add_location_alt_outlined), label: Text(context.tr('Add stop'))),
        const SizedBox(height: AppSpacing.md),
      ],
    );
  }
}
