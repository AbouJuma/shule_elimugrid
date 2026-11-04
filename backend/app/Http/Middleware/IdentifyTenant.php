<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant EXCLUSIVELY from the authenticated user's
 * school_id and binds it into TenantContext + Spatie's permission team
 * resolver. Never reads a school/tenant identifier from headers, query
 * params, or request body — a spoofed "school_id" in a request must have
 * zero effect on which tenant's data is visible.
 *
 * Platform users (school_id === null, e.g. Super Admin) proceed with no
 * tenant bound; BelongsToTenant-scoped models then intentionally expose
 * all tenants to them.
 */
class IdentifyTenant
{
    public function __construct(protected TenantContext $tenantContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->school_id) {
            $school = $user->school;

            if (! $school || $school->status === 'suspended') {
                abort(403, 'This school account is suspended. Contact the platform administrator.');
            }

            $this->tenantContext->set($school);
            setPermissionsTeamId($school->id);

            return $next($request);
        }

        // Explicitly clear rather than leaving whatever a PRIOR request bound —
        // TenantContext and Spatie's permission team are both singletons, so
        // under any long-lived-process model (a queue worker, Octane, or simply
        // this app's own test suite issuing several requests per test) a
        // platform user's request would otherwise silently inherit the tenant
        // scope of whichever request ran just before it. Not a plain PHP-FPM
        // production risk (a fresh process boots per request there), but cheap
        // and worth doing regardless of which model this ends up deployed on.
        $this->tenantContext->set(null);
        setPermissionsTeamId(null);

        return $next($request);
    }
}
