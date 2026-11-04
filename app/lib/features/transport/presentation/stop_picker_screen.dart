import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import '../../../core/config/app_config.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';

/// A stop being built in the route editor.
class StopDraft {
  StopDraft({this.id, required this.name, this.point, this.pickupTime});

  /// Server id of an existing stop (kept so editing a route doesn't recreate it).
  final int? id;
  String name;
  LatLng? point;

  /// HH:mm, or null.
  String? pickupTime;
}

class _PlaceHit {
  const _PlaceHit(this.name, this.point);

  final String name;
  final LatLng point;
}

/// Place a stop on the map: search for a place, tap the map, or use the
/// phone's current position; then name it and (optionally) give a pick-up time.
class StopPickerScreen extends ConsumerStatefulWidget {
  const StopPickerScreen({super.key, this.initial, this.near = const []});

  final StopDraft? initial;

  /// Existing stops, to centre the map somewhere sensible.
  final List<LatLng> near;

  @override
  ConsumerState<StopPickerScreen> createState() => _StopPickerScreenState();
}

class _StopPickerScreenState extends ConsumerState<StopPickerScreen> {
  final _map = MapController();
  final _name = TextEditingController();
  final _search = TextEditingController();
  Timer? _debounce;
  LatLng? _point;
  String? _time;
  List<_PlaceHit> _hits = [];
  bool _searching = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _name.text = widget.initial?.name ?? '';
    _point = widget.initial?.point;
    _time = widget.initial?.pickupTime;
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _name.dispose();
    _search.dispose();
    super.dispose();
  }

  void _onSearchChanged(String q) {
    _debounce?.cancel();

    if (q.trim().length < 2) {
      setState(() => _hits = []);
      return;
    }

    _debounce = Timer(const Duration(milliseconds: 400), () async {
      setState(() => _searching = true);

      try {
        final body = await ref.read(apiClientProvider).get('/places/search', query: {'q': q.trim()});
        final hits = (body['data'] as List<dynamic>)
            .cast<Map<String, dynamic>>()
            .map((p) => _PlaceHit(p['name'] as String, LatLng((p['latitude'] as num).toDouble(), (p['longitude'] as num).toDouble())))
            .toList();

        if (mounted) setState(() => _hits = hits);
      } catch (_) {
        if (mounted) setState(() => _hits = []);
      } finally {
        if (mounted) setState(() => _searching = false);
      }
    });
  }

  void _choose(LatLng p, {String? name}) {
    setState(() {
      _point = p;
      _hits = [];
      _error = null;
      if (name != null && _name.text.trim().isEmpty) _name.text = name;
    });
    _map.move(p, 16);
  }

  Future<void> _useMyLocation() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        throw AppLocalizations.translate('Location permission was denied.');
      }

      final p = await Geolocator.getCurrentPosition();
      if (!mounted) return;
      _choose(LatLng(p.latitude, p.longitude));
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<void> _pickTime() async {
    final parts = (_time ?? '07:00').split(':');
    final picked = await showTimePicker(context: context, initialTime: TimeOfDay(hour: int.tryParse(parts[0]) ?? 7, minute: int.tryParse(parts[1]) ?? 0));
    if (picked != null) setState(() => _time = '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}');
  }

  void _done() {
    if (_name.text.trim().isEmpty) {
      setState(() => _error = context.tr('Give the stop a name.'));
      return;
    }

    if (_point == null) {
      setState(() => _error = context.tr('Choose the stop\'s position on the map.'));
      return;
    }

    Navigator.of(context).pop(StopDraft(id: widget.initial?.id, name: _name.text.trim(), point: _point, pickupTime: _time));
  }

  @override
  Widget build(BuildContext context) {
    final center = _point ?? widget.near.firstOrNull ?? const LatLng(-6.7924, 39.2083);

    return Scaffold(
      appBar: AppBar(title: Text(context.tr(widget.initial == null ? 'Add stop' : 'Edit stop'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.sm),
            child: TextField(
              controller: _search,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: context.tr('Search a place…'),
                prefixIcon: const Icon(Icons.search),
                isDense: true,
                suffixIcon: _searching ? const Padding(padding: EdgeInsets.all(12), child: SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))) : null,
              ),
            ),
          ),
          if (_hits.isNotEmpty)
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 180),
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final h in _hits)
                    ListTile(dense: true, leading: const Icon(Icons.place_outlined), title: Text(h.name), onTap: () => _choose(h.point, name: h.name)),
                ],
              ),
            ),
          Expanded(
            child: Stack(
              children: [
                FlutterMap(
                  mapController: _map,
                  options: MapOptions(
                    initialCenter: center,
                    initialZoom: _point != null ? 16 : 13,
                    onTap: (_, p) => _choose(p),
                  ),
                  children: [
                    TileLayer(urlTemplate: AppConfig.tileUrlTemplate, userAgentPackageName: 'com.elimugrid.elimugrid_app'),
                    MarkerLayer(
                      markers: [
                        for (final p in widget.near)
                          Marker(point: p, width: 18, height: 18, child: Container(decoration: BoxDecoration(color: AppColors.navy700.withValues(alpha: 0.6), shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2)))),
                        if (_point != null) Marker(point: _point!, width: 40, height: 40, alignment: Alignment.topCenter, child: const Icon(Icons.location_on, size: 40, color: AppColors.error600)),
                      ],
                    ),
                    const SimpleAttributionWidget(source: Text('OpenStreetMap contributors')),
                  ],
                ),
                Positioned(
                  right: 12,
                  top: 12,
                  child: FloatingActionButton.small(heroTag: null, tooltip: context.tr('Use my location'), onPressed: _useMyLocation, child: const Icon(Icons.my_location)),
                ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(child: TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: InputDecoration(labelText: '${context.tr('Stop name')} *', isDense: true))),
                      const SizedBox(width: AppSpacing.sm),
                      OutlinedButton.icon(onPressed: _pickTime, icon: const Icon(Icons.schedule, size: 18), label: Text(_time ?? context.tr('Time'))),
                    ],
                  ),
                  if (_point != null)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.xs),
                      child: Text('${_point!.latitude.toStringAsFixed(5)}, ${_point!.longitude.toStringAsFixed(5)}', style: AppTypography.bodySm),
                    ),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.xs),
                      child: Text(_error!, style: AppTypography.bodySm.copyWith(color: AppColors.error600)),
                    ),
                  const SizedBox(height: AppSpacing.sm),
                  FilledButton(onPressed: _done, child: Text(context.tr('Save stop'))),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
