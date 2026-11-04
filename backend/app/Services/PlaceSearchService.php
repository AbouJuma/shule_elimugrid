<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Looks up real-world places by name so staff can pick a route stop
 * without knowing its latitude/longitude. Checks our own growing local
 * cache first (instant, no external dependency), and only falls back to
 * Nominatim (OpenStreetMap's free public geocoder — no API key, chosen to
 * match the app's OSM-based mapping elsewhere) when the local cache comes
 * up short. Every Nominatim result found is cached into `places`, so the
 * same search for the next school is answered locally.
 *
 * Search area is fixed to Tanzania (`countrycodes=tz`) since that's this
 * deployment's initial market — revisit if/when other countries onboard.
 *
 * Nominatim's usage policy caps the public instance at ~1 request/second
 * and requires a real identifying User-Agent; this is fine for an admin
 * typing a stop name occasionally, but would need a self-hosted Nominatim
 * or a paid provider before this could support true autocomplete-on-every-
 * keystroke at scale.
 */
class PlaceSearchService
{
    public function search(string $query, int $limit = 8): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $local = Place::query()->where('name', 'like', "%{$query}%")->limit($limit)->get();

        // Prefer what we already know. Only pay for an external geocoding
        // call when we have nothing local to offer at all — not merely to
        // pad out a short list — both to respect Nominatim's fair-use
        // limits and because a school's own previously-used stops are
        // more useful matches than a fresh geocoder result anyway.
        if ($local->isNotEmpty()) {
            return $local;
        }

        $remote = $this->searchNominatim($query, $limit);

        $cached = $remote->map(fn (array $result) => Place::firstOrCreate([
            'name' => $result['name'],
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
        ], ['source' => 'nominatim']));

        return $local->concat($cached)->unique('id')->values();
    }

    protected function searchNominatim(string $query, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'ElimuGrid-School-ERP/1.0 (contact: support@elimugrid.test)',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'json',
                'countrycodes' => 'tz',
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim place search failed', ['query' => $query, 'error' => $e->getMessage()]);

            return collect();
        }

        if (! $response->successful()) {
            return collect();
        }

        return collect($response->json())
            ->filter(fn ($item) => isset($item['lat'], $item['lon'], $item['display_name']))
            ->map(fn (array $item) => [
                'name' => Str::limit($item['display_name'], 250, ''),
                'latitude' => (float) $item['lat'],
                'longitude' => (float) $item['lon'],
            ]);
    }
}
