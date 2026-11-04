<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TransportRoute extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'transport_routes';

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'vehicle_id', 'name', 'status'];

    protected static function booted(): void
    {
        static::creating(function (TransportRoute $route) {
            $route->uuid ??= (string) Str::uuid();
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
