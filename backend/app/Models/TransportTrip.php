<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TransportTrip extends Model
{
    use BelongsToTenant;

    public const DIRECTIONS = ['morning', 'afternoon'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'vehicle_id', 'transport_route_id', 'driver_user_id', 'direction', 'status', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (TransportTrip $trip) {
            $trip->uuid ??= (string) Str::uuid();
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(TripStopVisit::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }
}
