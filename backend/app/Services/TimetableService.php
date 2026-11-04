<?php

namespace App\Services;

use App\Models\School;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    /**
     * Puts a lesson into a class's slot (replacing whatever was there),
     * refusing anything that would put a teacher, or a class's stream, in
     * two places at once. All lookups filter by school_id explicitly.
     *
     * @param  array{school_class_id: int, stream_id?: ?int, day_of_week: int, timetable_period_id: int, subject_id: int, teacher_user_id?: ?int, room?: ?string}  $data
     */
    public function setSlot(School $school, array $data): TimetableEntry
    {
        return DB::transaction(function () use ($school, $data) {
            $period = TimetablePeriod::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($data['timetable_period_id']);

            if ($period->is_break) {
                throw ValidationException::withMessages(['timetable_period_id' => [__('Lessons cannot be scheduled in a break.')]]);
            }

            $streamId = $data['stream_id'] ?? null;
            $teacherId = $data['teacher_user_id'] ?? null;

            $sameSlot = fn () => TimetableEntry::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('day_of_week', $data['day_of_week'])
                ->where('timetable_period_id', $data['timetable_period_id']);

            // A whole-class lesson (no stream) occupies every stream, and a
            // stream lesson conflicts with a whole-class one in the same slot.
            $classSlot = $sameSlot()->where('school_class_id', $data['school_class_id']);
            $existing = (clone $classSlot)->where('stream_id', $streamId)->first();

            $overlapping = (clone $classSlot)
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->when($streamId !== null, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('stream_id')->orWhere('stream_id', $streamId)))
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages(['timetable_period_id' => [__('This slot already has a lesson for that class or stream.')]]);
            }

            if ($teacherId) {
                $busy = $sameSlot()->where('teacher_user_id', $teacherId)
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->with('schoolClass')->first();

                if ($busy) {
                    throw ValidationException::withMessages(['teacher_user_id' => [
                        __('This teacher is already teaching :class in that slot.', ['class' => $busy->schoolClass->name]),
                    ]]);
                }
            }

            $room = trim((string) ($data['room'] ?? ''));
            if ($room !== '') {
                $roomBusy = $sameSlot()->where('room', $room)
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->with('schoolClass')->first();

                if ($roomBusy) {
                    throw ValidationException::withMessages(['room' => [
                        __('Room :room is already used by :class in that slot.', ['room' => $room, 'class' => $roomBusy->schoolClass->name]),
                    ]]);
                }
            }

            $attributes = [
                'subject_id' => $data['subject_id'],
                'teacher_user_id' => $teacherId,
                'room' => $data['room'] ?? null,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return TimetableEntry::withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'school_class_id' => $data['school_class_id'],
                'stream_id' => $streamId,
                'day_of_week' => $data['day_of_week'],
                'timetable_period_id' => $data['timetable_period_id'],
                ...$attributes,
            ]);
        });
    }
}
