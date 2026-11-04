<?php

namespace App\Services;

use App\Models\School;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\TimetableRequirement;
use Illuminate\Support\Facades\DB;

/**
 * Fills the empty slots of the weekly timetable from each class's
 * requirements (subject, teacher, lessons per week).
 *
 * It is a greedy scheduler, not an optimiser: requirements are placed one
 * lesson at a time in rounds (so no subject hogs the best slots), preferring
 * days where the subject has the fewest lessons so far, and never
 *   - putting a teacher in two classes at once,
 *   - double-booking a class,
 *   - using a break, or a day the school does not run.
 * Lessons already in the grid (placed by hand, or kept from an earlier run)
 * are never moved and count towards the target. Whatever cannot be placed is
 * reported with a reason instead of being forced in.
 *
 * Whole-class lessons only — per-stream requirements are not modelled.
 */
class TimetableGenerator
{
    /**
     * @return array{placed: int, wanted: int, unplaced: array<int, array{class: string, subject: string, missing: int, reason: string}>}
     */
    public function generate(School $school, bool $replaceGenerated = false): array
    {
        return DB::transaction(function () use ($school, $replaceGenerated) {
            if ($replaceGenerated) {
                TimetableEntry::withoutGlobalScopes()->where('school_id', $school->id)->where('generated', true)->delete();
            }

            $periods = TimetablePeriod::withoutGlobalScopes()->where('school_id', $school->id)
                ->where('is_break', false)->orderBy('sort_order')->orderBy('start_time')->pluck('id')->all();
            $days = $school->schoolDays();

            $requirements = TimetableRequirement::withoutGlobalScopes()->where('school_id', $school->id)
                ->where('periods_per_week', '>', 0)->with(['schoolClass', 'subject'])->get();

            // ---- Occupancy from what is already in the grid ---------------------------------
            $classBusy = [];   // [class][day][period] => true
            $teacherBusy = []; // [teacher][day][period] => true
            $have = [];        // [class][subject] => lessons already there
            $perDay = [];      // [class][subject][day] => lessons already there

            foreach (TimetableEntry::withoutGlobalScopes()->where('school_id', $school->id)->get() as $e) {
                $classBusy[$e->school_class_id][$e->day_of_week][$e->timetable_period_id] = true;

                if ($e->teacher_user_id) {
                    $teacherBusy[$e->teacher_user_id][$e->day_of_week][$e->timetable_period_id] = true;
                }

                if ($e->stream_id === null) {
                    $have[$e->school_class_id][$e->subject_id] = ($have[$e->school_class_id][$e->subject_id] ?? 0) + 1;
                    $perDay[$e->school_class_id][$e->subject_id][$e->day_of_week] = ($perDay[$e->school_class_id][$e->subject_id][$e->day_of_week] ?? 0) + 1;
                }
            }

            // Most constrained first: taught by a specific teacher, and the most lessons a week.
            $queue = $requirements
                ->map(fn ($r) => ['req' => $r, 'left' => max(0, $r->periods_per_week - ($have[$r->school_class_id][$r->subject_id] ?? 0))])
                ->sortByDesc(fn ($x) => [$x['req']->teacher_user_id ? 1 : 0, $x['left']])
                ->values()
                ->all();

            $wanted = array_sum(array_column($queue, 'left'));
            $placed = 0;
            $failed = []; // index => reason

            $newEntries = [];

            // ---- Rounds: one lesson per requirement per pass ----------------------------------
            do {
                $progress = false;

                foreach ($queue as $i => &$item) {
                    if ($item['left'] <= 0 || isset($failed[$i])) {
                        continue;
                    }

                    $r = $item['req'];
                    $slot = $this->bestSlot($r, $days, $periods, $classBusy, $teacherBusy, $perDay);

                    if ($slot === null) {
                        $failed[$i] = $this->reason($r, $days, $periods, $classBusy);

                        continue;
                    }

                    [$day, $period] = $slot;

                    $classBusy[$r->school_class_id][$day][$period] = true;

                    if ($r->teacher_user_id) {
                        $teacherBusy[$r->teacher_user_id][$day][$period] = true;
                    }

                    $perDay[$r->school_class_id][$r->subject_id][$day] = ($perDay[$r->school_class_id][$r->subject_id][$day] ?? 0) + 1;

                    $newEntries[] = [
                        'school_id' => $school->id, 'school_class_id' => $r->school_class_id, 'stream_id' => null,
                        'day_of_week' => $day, 'timetable_period_id' => $period, 'subject_id' => $r->subject_id,
                        'teacher_user_id' => $r->teacher_user_id, 'generated' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ];

                    $item['left']--;
                    $placed++;
                    $progress = true;
                }
                unset($item);
            } while ($progress);

            foreach (array_chunk($newEntries, 200) as $chunk) {
                TimetableEntry::withoutGlobalScopes()->insert($chunk);
            }

            $unplaced = [];

            foreach ($queue as $i => $item) {
                if ($item['left'] > 0) {
                    $unplaced[] = [
                        'class' => $item['req']->schoolClass->name,
                        'subject' => $item['req']->subject->name,
                        'missing' => $item['left'],
                        'reason' => $failed[$i] ?? 'no_slot',
                    ];
                }
            }

            return ['placed' => $placed, 'wanted' => $wanted, 'unplaced' => $unplaced];
        });
    }

    /**
     * The best free (day, period): the day where this subject already has the
     * fewest lessons, then the period it uses least — so a subject spreads over
     * the week instead of stacking. A subject never gets more than two lessons
     * a day unless its weekly total forces it.
     *
     * @return array{0: int, 1: int}|null
     */
    protected function bestSlot(TimetableRequirement $r, array $days, array $periods, array $classBusy, array $teacherBusy, array $perDay): ?array
    {
        $cap = max(2, (int) ceil($r->periods_per_week / max(1, count($days))));
        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($days as $day) {
            $onDay = $perDay[$r->school_class_id][$r->subject_id][$day] ?? 0;

            if ($onDay >= $cap) {
                continue;
            }

            foreach ($periods as $index => $period) {
                if (! empty($classBusy[$r->school_class_id][$day][$period])) {
                    continue;
                }

                if ($r->teacher_user_id && ! empty($teacherBusy[$r->teacher_user_id][$day][$period])) {
                    continue;
                }

                // Rotate the preferred period per subject so subjects don't all crowd period 1.
                $rotation = ($index + $r->subject_id) % max(1, count($periods));
                $score = $onDay * 1000 + $rotation * 10 + $day;

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = [$day, $period];
                }
            }
        }

        return $best;
    }

    protected function reason(TimetableRequirement $r, array $days, array $periods, array $classBusy): string
    {
        foreach ($days as $day) {
            foreach ($periods as $period) {
                if (empty($classBusy[$r->school_class_id][$day][$period])) {
                    // The class has room, so the blocker is the teacher's availability (or the daily cap).
                    return $r->teacher_user_id ? 'teacher_busy' : 'daily_cap';
                }
            }
        }

        return 'class_full';
    }
}
