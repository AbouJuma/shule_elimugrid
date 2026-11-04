<?php

namespace App\Services;

use App\Models\GradeScale;
use App\Models\School;
use Illuminate\Support\Collection;

class GradingService
{
    /** Highest boundary first. Seeds the defaults the first time a school asks. */
    public function scaleFor(School $school): Collection
    {
        $rows = GradeScale::withoutGlobalScopes()->where('school_id', $school->id)->orderByDesc('min_percent')->get();

        if ($rows->isEmpty()) {
            foreach (config('grading.defaults') as $default) {
                GradeScale::withoutGlobalScopes()->create(['school_id' => $school->id, ...$default]);
            }

            $rows = GradeScale::withoutGlobalScopes()->where('school_id', $school->id)->orderByDesc('min_percent')->get();
        }

        return $rows;
    }

    /** @return array{grade: string, remark: ?string} */
    public function gradeFor(Collection $scale, ?float $percent): array
    {
        if ($percent === null) {
            return ['grade' => '—', 'remark' => null];
        }

        $match = $scale->first(fn ($row) => $percent >= $row->min_percent) ?? $scale->last();

        // The school may have typed a Swahili remark; use it when the page is in Swahili.
        $remark = app()->getLocale() === 'sw' && filled($match->remark_sw) ? $match->remark_sw : $match->remark;

        return ['grade' => $match->grade, 'remark' => $remark];
    }

    /**
     * Replaces the school's scale wholesale.
     *
     * @param  array<int, array{grade: string, min_percent: int, remark?: ?string, remark_sw?: ?string}>  $rows
     */
    public function replaceScale(School $school, array $rows): void
    {
        GradeScale::withoutGlobalScopes()->where('school_id', $school->id)->delete();

        foreach ($rows as $row) {
            GradeScale::withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'grade' => strtoupper($row['grade']),
                'min_percent' => $row['min_percent'],
                'remark' => $row['remark'] ?? null,
                'remark_sw' => $row['remark_sw'] ?? null,
            ]);
        }
    }
}
