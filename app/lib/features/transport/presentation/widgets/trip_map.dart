import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../../../../core/config/app_config.dart';
import '../../../../core/i18n/app_localizations.dart';
import '../../../../core/network/road_router.dart';
import '../../../../core/theme/app_colors.dart';
import '../../domain/trip_models.dart';

/// The route on a map: stops as numbered pins (green = reached, gold = next,
/// navy = still to come), a dashed line joining the stops in visiting order,
/// a solid gold line for where the bus has actually been, and the bus itself.
///
/// Between the stops the line follows the roads when the routing service
/// (AppConfig.routingUrl) answers; until then, or if it can't, it is a dashed
/// straight line that shows the visiting order but not the roads.
class TripMap extends StatefulWidget {
  const TripMap({super.key, required this.stops, this.trail = const [], this.position, this.follow = true, this.highlightStopId});

  final List<TripStop> stops;
  final List<LatLng> trail;
  final TripPosition? position;

  /// A stop to draw larger with a ring (a parent's child's stop).
  final int? highlightStopId;

  /// Keep the bus centred as it moves (the driver can pan away; the button re-centres).
  final bool follow;

  @override
  State<TripMap> createState() => _TripMapState();
}

class _TripMapState extends State<TripMap> {
  final _controller = MapController();

  bool _following = true;
  bool _fitted = false;
  List<LatLng>? _road;
  String? _roadKey;

  @override
  void initState() {
    super.initState();
    _loadRoad();
  }

  /// Ask for the road line for the current stops, once per distinct set of points.
  void _loadRoad() {
    final points = _stopPoints;
    final key = RoadRouter.keyFor(points);

    if (key == _roadKey) return;
    _roadKey = key;
    _road = RoadRouter.instance.cached(points);
    if (_road != null) return;

    RoadRouter.instance.route(points).then((line) {
      if (!mounted || key != _roadKey || line == null) return;
      setState(() => _road = line);
    });
  }

  List<LatLng> get _stopPoints => widget.stops.map((s) => s.point).whereType<LatLng>().toList();

  List<LatLng> get _allPoints => [..._stopPoints, ...widget.trail, if (widget.position != null) widget.position!.point];

  @override
  void didUpdateWidget(covariant TripMap old) {
    super.didUpdateWidget(old);
    _loadRoad();

    final p = widget.position;
    if (widget.follow && _following && p != null && p.point != old.position?.point && _fitted) {
      _controller.move(p.point, _controller.camera.zoom);
    }
  }

  void _fitAll() {
    final points = _allPoints;
    if (points.isEmpty) return;

    if (points.length == 1) {
      _controller.move(points.first, 15);
    } else {
      _controller.fitCamera(CameraFit.coordinates(coordinates: points, padding: const EdgeInsets.all(56), maxZoom: 16));
    }
  }

  Color _pinColour(TripStop s) => s.arrived ? AppColors.green700 : (s.isNext ? AppColors.gold600 : AppColors.navy700);

  @override
  Widget build(BuildContext context) {
    final points = _allPoints;
    final center = points.isNotEmpty ? points.first : const LatLng(-6.7924, 39.2083);
    final stopPoints = _stopPoints;

    return Stack(
      children: [
        FlutterMap(
          mapController: _controller,
          options: MapOptions(
            initialCenter: center,
            initialZoom: 13,
            onMapReady: () {
              _fitted = true;
              _fitAll();
            },
            onPositionChanged: (_, byGesture) {
              if (byGesture && _following) setState(() => _following = false);
            },
          ),
          children: [
            TileLayer(
              urlTemplate: AppConfig.tileUrlTemplate,
              userAgentPackageName: 'com.elimugrid.elimugrid_app',
              errorTileCallback: (tile, error, stackTrace) => debugPrint('MAP TILE ERROR ${tile.coordinates}: $error'),
            ),
            if (_road != null)
              PolylineLayer(polylines: [Polyline(points: _road!, strokeWidth: 4, color: AppColors.navy700.withValues(alpha: 0.7))])
            else if (stopPoints.length > 1)
              PolylineLayer(
                polylines: [
                  Polyline(points: stopPoints, strokeWidth: 3, color: AppColors.navy700.withValues(alpha: 0.55), pattern: StrokePattern.dashed(segments: const [8, 8])),
                ],
              ),
            if (widget.trail.length > 1)
              PolylineLayer(polylines: [Polyline(points: widget.trail, strokeWidth: 5, color: AppColors.gold600)]),
            MarkerLayer(
              markers: [
                for (var i = 0; i < widget.stops.length; i++)
                  if (widget.stops[i].point != null)
                    Marker(
                      point: widget.stops[i].point!,
                      width: widget.stops[i].id == widget.highlightStopId ? 38 : 30,
                      height: widget.stops[i].id == widget.highlightStopId ? 38 : 30,
                      child: Container(
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: _pinColour(widget.stops[i]),
                          shape: BoxShape.circle,
                          border: Border.all(color: widget.stops[i].id == widget.highlightStopId ? AppColors.gold500 : Colors.white, width: widget.stops[i].id == widget.highlightStopId ? 4 : 2),
                          boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 4)],
                        ),
                        child: widget.stops[i].arrived
                            ? const Icon(Icons.check, size: 16, color: Colors.white)
                            : Text('${i + 1}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                      ),
                    ),
                if (widget.position != null)
                  Marker(
                    point: widget.position!.point,
                    width: 44,
                    height: 44,
                    child: Container(
                      decoration: BoxDecoration(
                        color: AppColors.gold500,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 3),
                        boxShadow: const [BoxShadow(color: Colors.black45, blurRadius: 8)],
                      ),
                      child: const Icon(Icons.directions_bus_rounded, color: AppColors.navy900, size: 24),
                    ),
                  ),
              ],
            ),
            const SimpleAttributionWidget(source: Text('OpenStreetMap contributors')),
          ],
        ),
        Positioned(
          right: 12,
          top: 12,
          child: Column(
            children: [
              FloatingActionButton.small(
                heroTag: null,
                tooltip: context.tr('Show the whole route'),
                onPressed: _fitAll,
                child: const Icon(Icons.zoom_out_map),
              ),
              if (widget.position != null) ...[
                const SizedBox(height: 8),
                FloatingActionButton.small(
                  heroTag: null,
                  tooltip: context.tr('Follow the bus'),
                  backgroundColor: _following ? AppColors.gold500 : null,
                  onPressed: () {
                    setState(() => _following = true);
                    _controller.move(widget.position!.point, 16);
                  },
                  child: const Icon(Icons.my_location),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
