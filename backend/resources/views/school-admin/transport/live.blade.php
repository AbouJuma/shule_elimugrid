@extends('layouts.portal')

@section('title', __('Live buses').' — ElimuGrid')
@section('page-title', __('Live buses'))

@section('sidebar')
    @include('school-admin.partials.sidebar', ['active' => 'transport-live'])
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        #liveMap { height: 68vh; min-height: 420px; border-radius: .5rem; border: 1px solid #e2e5ea; }
        .stop-pin { width: 26px; height: 26px; border-radius: 50%; color: #fff; font-weight: 700; font-size: 12px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.35); }
        .bus-pin { width: 38px; height: 38px; border-radius: 50%; background: #d4a017; color: #0a1f44; font-size: 20px; display: flex; align-items: center; justify-content: center; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.45); }
        .trip-item { cursor: pointer; }
        .trip-item.active { background: #fbf1d6; }
    </style>
@endpush

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span>{{ __('Trips in progress') }}</span>
                    <span class="small text-secondary" id="updated">…</span>
                </div>
                <div id="tripList" class="list-group list-group-flush"></div>
            </div>
            <p class="small text-secondary mt-2 mb-0">
                {{ __('Positions come from the driver\'s phone while the trip is running. The map refreshes every 5 seconds. The dashed line joins the stops in order; the solid line is where the bus has actually been.') }}
            </p>
        </div>
        <div class="col-lg-8"><div id="liveMap"></div></div>
    </div>
@endsection

@php
    $labels = [
        'none' => __('No trip is running right now.'), 'stopsDone' => __(':a of :b stops', ['a' => ':a', 'b' => ':b']),
        'next' => __('Next stop'), 'seen' => __('last seen :s ago'), 'noGps' => __('No position received yet'),
        'morning' => __('Morning pick-up'), 'afternoon' => __('Afternoon drop-off'), 'students' => __('students'),
        'stale' => __('No update for a while'), 'min' => __('min'),
    ];
@endphp
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
        (function () {
            const dataUrl = @json(route('school-admin.transport-live.data'));
            const t = @json($labels);

            const map = L.map('liveMap').setView([-6.79, 39.21], 11);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

            const layers = {};        // trip id -> L.LayerGroup
            let selected = null;
            let fitted = false;

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const ago = (iso) => { const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000)); return s < 90 ? s + 's' : Math.round(s / 60) + ' min'; };

            function pin(n, colour) {
                return L.divIcon({ className: '', html: '<div class="stop-pin" style="background:' + colour + '">' + n + '</div>', iconSize: [26, 26], iconAnchor: [13, 13] });
            }

            function draw(trip) {
                const g = L.layerGroup();
                const pts = [];

                trip.stops.forEach((s, i) => {
                    if (s.latitude == null) return;
                    pts.push([s.latitude, s.longitude]);
                    const colour = s.arrived_at ? '#1b7a4d' : (s.is_next ? '#d4a017' : '#17376b');
                    L.marker([s.latitude, s.longitude], { icon: pin(i + 1, colour) })
                        .bindPopup('<strong>' + esc(s.name) + '</strong><br>' + s.students + ' ' + t.students + (s.arrived_at ? '<br>✔ ' + new Date(s.arrived_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''))
                        .addTo(g);
                });

                if (pts.length > 1) L.polyline(pts, { color: '#17376b', weight: 3, opacity: .55, dashArray: '6 8' }).addTo(g);
                if (trip.trail.length > 1) L.polyline(trip.trail, { color: '#d4a017', weight: 5, opacity: .9 }).addTo(g);

                if (trip.position) {
                    L.marker([trip.position.latitude, trip.position.longitude], {
                        icon: L.divIcon({ className: '', html: '<div class="bus-pin">🚌</div>', iconSize: [38, 38], iconAnchor: [19, 19] }), zIndexOffset: 1000,
                    }).bindPopup('<strong>' + esc(trip.vehicle.registration_number) + '</strong><br>' + esc(trip.vehicle.driver?.name ?? '')
                        + (trip.position.speed_kmh != null ? '<br>' + Math.round(trip.position.speed_kmh) + ' km/h' : '')).addTo(g);
                }

                return { group: g, bounds: pts.concat(trip.position ? [[trip.position.latitude, trip.position.longitude]] : []) };
            }

            function render(trips) {
                Object.values(layers).forEach((l) => map.removeLayer(l.group));
                Object.keys(layers).forEach((k) => delete layers[k]);

                const list = document.getElementById('tripList');
                list.innerHTML = trips.length ? '' : '<div class="list-group-item text-secondary small">' + esc(t.none) + '</div>';

                if (selected && !trips.some((x) => x.id === selected)) selected = null;
                const allBounds = [];

                trips.forEach((trip) => {
                    const d = draw(trip);
                    d.group.addTo(map);
                    layers[trip.id] = d;
                    allBounds.push(...d.bounds);

                    const next = trip.stops.find((s) => s.is_next);
                    const stale = trip.position && (Date.now() - new Date(trip.position.recorded_at).getTime()) > 120000;
                    const item = document.createElement('div');
                    item.className = 'list-group-item trip-item' + (trip.id === selected ? ' active' : '');
                    item.innerHTML = '<div class="d-flex justify-content-between"><strong>' + esc(trip.vehicle.registration_number) + '</strong>'
                        + '<span class="small text-secondary">' + esc(t[trip.direction]) + '</span></div>'
                        + '<div>' + esc(trip.route.name) + ' · ' + esc(trip.vehicle.driver?.name ?? '') + '</div>'
                        + '<div class="progress my-1" style="height:6px"><div class="progress-bar bg-success" style="width:' + (trip.progress.total ? trip.progress.visited / trip.progress.total * 100 : 0) + '%"></div></div>'
                        + '<div class="small text-secondary">' + esc(t.stopsDone.replace(':a', trip.progress.visited).replace(':b', trip.progress.total))
                        + (next ? ' · ' + esc(t.next) + ': ' + esc(next.name) + (trip.next ? ' (≈ ' + trip.next.eta_minutes + ' ' + esc(t.min) + ' · ' + (trip.next.distance_m >= 1000 ? (trip.next.distance_m / 1000).toFixed(1) + ' km' : trip.next.distance_m + ' m') + ')' : '') : '') + '</div>'
                        + '<div class="small ' + (stale ? 'text-warning' : 'text-secondary') + '">'
                        + (trip.position ? esc(t.seen.replace(':s', ago(trip.position.recorded_at))) + (stale ? ' — ' + esc(t.stale) : '') : esc(t.noGps)) + '</div>';
                    item.addEventListener('click', () => { selected = trip.id; fitTo(d.bounds); render(trips); });
                    list.appendChild(item);
                });

                if (!fitted && allBounds.length) { fitTo(allBounds); fitted = true; }
                document.getElementById('updated').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }

            function fitTo(bounds) { if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 }); }

            async function refresh() {
                try {
                    const r = await fetch(dataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (r.ok) render((await r.json()).data);
                } catch (e) { /* network blip — try again next tick */ }
            }

            refresh();
            setInterval(refresh, 5000);
        })();
    </script>
@endpush
