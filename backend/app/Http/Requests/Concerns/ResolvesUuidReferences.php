<?php

namespace App\Http\Requests\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The API exposes records by uuid (vehicles, users, routes…), but the
 * database — and the older integer-id validation rules — use internal ids.
 * This lets a request field carry either: a uuid is swapped for the matching
 * id *within the caller's school* before validation. A uuid that matches
 * nothing is left as-is, so the normal "exists" rule rejects it.
 */
trait ResolvesUuidReferences
{
    protected function resolveUuid(string $field, string $table): void
    {
        $value = $this->input($field);

        if (! is_string($value) || ! Str::isUuid($value)) {
            return;
        }

        $id = DB::table($table)->where('uuid', $value)->where('school_id', app(TenantContext::class)->id())->value('id');

        if ($id !== null) {
            $this->merge([$field => $id]);
        }
    }

    /** Same, for a list of references (e.g. `student_ids`): each uuid in the array is swapped for its id. */
    protected function resolveUuidList(string $field, string $table): void
    {
        $values = $this->input($field);

        if (! is_array($values)) {
            return;
        }

        $uuids = array_values(array_filter($values, fn ($v) => is_string($v) && Str::isUuid($v)));
        $map = $uuids ? DB::table($table)->whereIn('uuid', $uuids)->where('school_id', app(TenantContext::class)->id())->pluck('id', 'uuid') : collect();

        $this->merge([$field => array_map(fn ($v) => is_string($v) && $map->has($v) ? $map[$v] : $v, $values)]);
    }
}
