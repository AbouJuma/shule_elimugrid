<?php

namespace App\Support\Pagination;

use Illuminate\Http\Request;

/**
 * Resolves a user-chosen page size from `?per_page=`, used by every
 * paginated portal list (see the page-size <select> in
 * school-admin/partials/table-pagination.blade.php). Only ever returns
 * one of ALLOWED — an unrecognized or missing value falls back to
 * $default rather than letting a client request an arbitrary page size
 * (e.g. per_page=999999 as a cheap denial-of-service against a large
 * table).
 */
class PageSize
{
    public const ALLOWED = [20, 50, 100, 500, 1000];

    public static function resolve(Request $request, int $default = 20): int
    {
        $requested = $request->integer('per_page', $default);

        return in_array($requested, self::ALLOWED, true) ? $requested : $default;
    }
}
