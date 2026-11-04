<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Support\Tenancy\TenantContext;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'uuid',
        'school_id',
        'campus_id',
        'name',
        'email',
        'phone',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->uuid ??= (string) Str::uuid();
        });

        // Users are tenant-owned like any other record, but unlike
        // BelongsToTenant this intentionally allows a null school_id
        // (Super Admin / platform users) and never auto-throws, since
        // Super Admin explicitly creates the first School Administrator
        // with a target school_id while operating outside any tenant
        // context.
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenant = app(TenantContext::class);

            if ($tenant->check()) {
                $builder->where($builder->qualifyColumn('school_id'), $tenant->id());
            }
        });
    }

    public function scopeAllTenants(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('tenant');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function isPlatformUser(): bool
    {
        return $this->school_id === null;
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class, 'driver_user_id');
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
