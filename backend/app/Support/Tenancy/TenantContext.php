<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Request-scoped holder for the "current tenant". Bound as a singleton and
 * populated exclusively by App\Http\Middleware\IdentifyTenant from the
 * authenticated user's school_id — never from a header, query param, or
 * any other client-controlled input. Global scopes and services read the
 * tenant from here rather than from auth()->user() directly, so platform
 * (Super Admin) contexts and queued jobs can also set it explicitly.
 */
class TenantContext
{
    protected ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): int|string|null
    {
        return $this->school?->id;
    }

    public function check(): bool
    {
        return $this->school !== null;
    }

    /**
     * True when operating with no tenant bound, i.e. a platform/Super Admin
     * request. Distinguishes "no tenant" from "tenant not yet resolved".
     */
    public function isPlatform(): bool
    {
        return $this->school === null;
    }

    /**
     * Guard for endpoints that are meaningless (or dangerous) without a
     * bound tenant — e.g. school-scoped role/user management, where a
     * platform/Super Admin caller must never fall through to querying
     * rows keyed by a null school_id. Throws rather than returning bool
     * so call sites can't forget to check it.
     */
    public function assertBound(): School
    {
        if ($this->school === null) {
            throw new HttpException(403, 'This action requires a school context.');
        }

        return $this->school;
    }
}
