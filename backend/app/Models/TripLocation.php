<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['transport_trip_id', 'latitude', 'longitude', 'speed_kmh', 'recorded_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'speed_kmh' => 'float', 'recorded_at' => 'datetime'];
    }
}
