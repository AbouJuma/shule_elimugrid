<?php

namespace App\Support\Tenancy;

use Spatie\Permission\DefaultTeamResolver;

/**
 * Identical behaviour to Spatie's default resolver — kept as our own class
 * so the tenant/team id is only ever set explicitly by TenantContext
 * (from the authenticated user's school_id), never inferred from a
 * session value or client-supplied input.
 */
class PermissionTeamResolver extends DefaultTeamResolver
{
}
