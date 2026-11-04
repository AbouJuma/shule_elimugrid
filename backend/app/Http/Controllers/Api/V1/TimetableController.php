<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Illuminate\Http\Request;

/**
 * Read-only timetable for the mobile app. Editing stays a portal task.
 */
class TimetableController extends Controller
{
    /** The signed-in staff member's own lessons — no permission needed, it's their own week. */
    public function mine(Request $request)
    {
        $entries = TimetableEntry::query()
            ->with(['subject', 'schoolClass', 'stream'])
            ->where('teacher_user_id', $request->user()->id)
            ->get();

        return response()->json(['data' => $this->payload($entries)]);
    }

    public function schoolClass(Request $request, SchoolClass $schoolClass)
    {
        $this->authorize('timetable.view');

        $streamId = $request->integer('stream_id') ?: null;

        $entries = TimetableEntry::query()
            ->with(['subject', 'teacher', 'stream', 'schoolClass'])
            ->where('school_class_id', $schoolClass->id)
            ->when($streamId,
                fn ($q) => $q->where(fn ($q2) => $q2->where('stream_id', $streamId)->orWhereNull('stream_id')),
                fn ($q) => $q->whereNull('stream_id'))
            ->get();

        return response()->json(['data' => $this->payload($entries)]);
    }

    public function payload($entries): array
    {
        $periods = TimetablePeriod::query()->orderBy('sort_order')->orderBy('start_time')->get();

        return [
            'days' => collect([...app(\App\Support\Tenancy\TenantContext::class)->assertBound()->schoolDays(), ...$entries->pluck('day_of_week')->all()])->unique()->sort()->values(),
            'periods' => $periods->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name,
                'start_time' => substr($p->start_time, 0, 5), 'end_time' => substr($p->end_time, 0, 5),
                'is_break' => $p->is_break,
            ])->values(),
            'entries' => $entries->map(fn ($e) => [
                'day_of_week' => $e->day_of_week,
                'period_id' => $e->timetable_period_id,
                'subject' => $e->subject->name,
                'teacher' => $e->teacher?->name,
                'class' => $e->schoolClass->name,
                'stream' => $e->stream?->name,
                'room' => $e->room,
            ])->values(),
        ];
    }
}
