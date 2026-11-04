<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The tenant root. Every school-owned record ultimately traces back to a
 * School via school_id. This model itself is platform-owned (managed by
 * Super Admin) and is intentionally NOT tenant-scoped.
 */
class School extends Model
{
    use HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'registration_number',
        'logo_path',
        'email',
        'phone',
        'website',
        'motto',
        'address',
        'timezone',
        'currency',
        'status',
        'subscription_package_id',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            $school->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * The weekdays this school holds lessons (1 = Monday … 7 = Sunday).
     * Defaults to Monday–Friday until the school chooses otherwise.
     *
     * @return array<int, int>
     */
    public function schoolDays(): array
    {
        $days = collect($this->settings['school_days'] ?? [1, 2, 3, 4, 5])
            ->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 1 && $d <= 7)->unique()->sort()->values()->all();

        return $days ?: [1, 2, 3, 4, 5];
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SchoolSubscription::class);
    }

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
