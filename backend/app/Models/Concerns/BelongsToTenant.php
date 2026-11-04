<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Applies tenant isolation to any model with a school_id column.
 *
 * - Every query against the model is automatically scoped to the current
 *   tenant (resolved from TenantContext, which is only ever set by
 *   IdentifyTenant middleware from the authenticated user — never from
 *   client input).
 * - New records are automatically stamped with the current tenant's
 *   school_id so callers cannot accidentally (or maliciously) write to
 *   another tenant by passing a different school_id in a request payload.
 * - Platform/Super Admin contexts (no tenant bound) see all tenants'
 *   data, since super admin tooling legitimately needs cross-tenant
 *   visibility (school management, platform analytics).
 *
 * This is a defense-in-depth layer, not the only one — policies and
 * service-layer checks must still validate tenant ownership explicitly.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenant = app(TenantContext::class);

            if ($tenant->check()) {
                $builder->where($builder->qualifyColumn('school_id'), $tenant->id());
            }
        });

        static::creating(function ($model) {
            $tenant = app(TenantContext::class);

            if (! $model->school_id && $tenant->check()) {
                $model->school_id = $tenant->id();
            }

            if (! $model->school_id) {
                throw new RuntimeException(
                    static::class.' requires a school_id and no tenant is bound in the current context.'
                );
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Explicit escape hatch for platform-level queries (e.g. Super Admin
     * reports) that must intentionally see every tenant's rows.
     */
    public function scopeAllTenants(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope('tenant');
    }
}
