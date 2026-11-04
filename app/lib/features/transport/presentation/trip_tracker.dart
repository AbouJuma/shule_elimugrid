import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../../auth/presentation/auth_controller.dart';
import 'trip_providers.dart';

class TrackerState {
  const TrackerState({this.tripId, this.lastSentAt, this.lastArrivedStop, this.error});

  final String? tripId;
  final DateTime? lastSentAt;
  final String? lastArrivedStop;

  /// A translatable message key, or null.
  final String? error;

  bool get isTracking => tripId != null;
}

/// Streams the driver's phone position to the running trip.
///
/// It lives for the whole signed-in session (the home shell watches it), so
/// leaving the trip screen never stops the tracking, and after an app
/// restart it resumes the driver's trip on its own. On Android the position
/// stream runs as a foreground service, so it keeps going with the screen off.
class TripTracker extends Notifier<TrackerState> {
  StreamSubscription<Position>? _subscription;
  Timer? _heartbeat;
  DateTime? _lastSent;
  bool _sending = false;

  @override
  TrackerState build() {
    ref.onDispose(_stop);

    final user = ref.watch(authControllerProvider).value?.user;
    final isDriver = user != null && user.can('transport.track') && !user.can('transport.manage');

    if (isDriver) Future.microtask(resume);

    return const TrackerState();
  }

  /// Picks up a trip that is already running (e.g. after the app restarted).
  Future<void> resume() async {
    try {
      final trip = await ref.read(tripRepositoryProvider).current();
      if (trip != null) await begin(trip.id);
    } catch (_) {
      // Offline at start-up: the trip screen will retry when opened.
    }
  }

  Future<void> begin(String tripId) async {
    _stop();

    if (!await Geolocator.isLocationServiceEnabled()) {
      state = TrackerState(tripId: tripId, error: 'Location services are turned off on this device.');
      return;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      state = TrackerState(tripId: tripId, error: 'Location permission was denied.');
      return;
    }

    state = TrackerState(tripId: tripId);

    final settings = defaultTargetPlatform == TargetPlatform.android
        ? AndroidSettings(
            accuracy: LocationAccuracy.high,
            distanceFilter: 8,
            intervalDuration: const Duration(seconds: 4),
            foregroundNotificationConfig: const ForegroundNotificationConfig(
              notificationTitle: 'ElimuGrid',
              notificationText: 'Trip in progress — sharing the bus location',
              enableWakeLock: true,
              setOngoing: true,
            ),
          )
        : (defaultTargetPlatform == TargetPlatform.iOS
            ? AppleSettings(
                accuracy: LocationAccuracy.high,
                distanceFilter: 8,
                activityType: ActivityType.automotiveNavigation,
                pauseLocationUpdatesAutomatically: false,
                showBackgroundLocationIndicator: true,
                allowBackgroundLocationUpdates: true,
              )
            : const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 8));

    _subscription = Geolocator.getPositionStream(locationSettings: settings).listen(
      (position) {
        // The stream can emit faster than we need; cap the upload rate.
        if (_lastSent == null || DateTime.now().difference(_lastSent!) >= const Duration(seconds: 4)) _send(position);
      },
      onError: (Object e) => state = TrackerState(tripId: tripId, error: 'Could not read the location.'),
    );

    // Backstop for the stream: a parked bus emits nothing (distance filter) and
    // some devices stop delivering updates, so every few seconds ask for a fresh
    // fix. That keeps "last seen" honest — it never re-sends a stale position.
    _heartbeat = Timer.periodic(const Duration(seconds: 8), (_) async {
      if (_lastSent != null && DateTime.now().difference(_lastSent!) < const Duration(seconds: 6)) return;

      try {
        final fresh = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 7)),
        );
        await _send(fresh);
      } catch (_) {
        // No fix right now (tunnel, GPS off). Leave "last seen" to age so the office sees it.
      }
    });

    // Send an immediate first fix so the trip shows a position straight away.
    try {
      final first = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high));
      await _send(first);
    } catch (_) {
      // The stream will deliver one shortly.
    }
  }

  Future<void> _send(Position p) async {
    final id = state.tripId;
    if (id == null || _sending) return;

    _sending = true;
    _lastSent = DateTime.now();

    try {
      final arrived = await ref.read(tripRepositoryProvider).sendLocation(
        id,
        latitude: p.latitude,
        longitude: p.longitude,
        speedKmh: p.speed >= 0 ? p.speed * 3.6 : null,
      );

      state = TrackerState(tripId: id, lastSentAt: DateTime.now(), lastArrivedStop: arrived ?? state.lastArrivedStop);
      if (arrived != null) ref.invalidate(driverTripProvider);
    } catch (e) {
      // 422 "trip already ended" (ended from another device / by the office): stop quietly.
      if (e.toString().contains('already ended')) {
        _stop();
        state = const TrackerState();
        ref.invalidate(driverTripProvider);
      } else {
        state = TrackerState(tripId: id, lastSentAt: state.lastSentAt, lastArrivedStop: state.lastArrivedStop, error: 'No connection — will retry.');
      }
    } finally {
      _sending = false;
    }
  }

  void end() {
    _stop();
    state = const TrackerState();
  }

  void _stop() {
    _subscription?.cancel();
    _subscription = null;
    _heartbeat?.cancel();
    _heartbeat = null;
    _lastSent = null;
  }
}

final tripTrackerProvider = NotifierProvider<TripTracker, TrackerState>(TripTracker.new);
