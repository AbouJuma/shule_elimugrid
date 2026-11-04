<?php

namespace App\Services;

use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place occupied_count is ever written — same row-locked
 * pattern as StockService/LibraryService, for the same reason:
 * concurrent allocate/vacate actions must never race and leave the
 * count wrong.
 */
class HostelService
{
    public function allocate(HostelRoom $room, Student $student, User $recordedBy): HostelAllocation
    {
        $existing = HostelAllocation::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'student_id' => ["{$student->fullName()} already has an active room allocation. Vacate it first."],
            ]);
        }

        return DB::transaction(function () use ($room, $student, $recordedBy) {
            $locked = HostelRoom::query()->lockForUpdate()->findOrFail($room->id);

            if ($locked->occupied_count >= $locked->capacity) {
                throw ValidationException::withMessages([
                    'hostel_room_id' => ["Room {$locked->room_number} is already at full capacity ({$locked->capacity})."],
                ]);
            }

            $locked->update(['occupied_count' => $locked->occupied_count + 1]);

            return HostelAllocation::create([
                'school_id' => $room->school_id,
                'hostel_room_id' => $room->id,
                'student_id' => $student->id,
                'allocated_date' => now(),
                'status' => 'active',
                'recorded_by' => $recordedBy->id,
            ]);
        });
    }

    public function vacate(HostelAllocation $allocation): void
    {
        if ($allocation->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['This allocation has already been vacated.'],
            ]);
        }

        DB::transaction(function () use ($allocation) {
            $locked = HostelRoom::query()->lockForUpdate()->findOrFail($allocation->hostel_room_id);
            $locked->update(['occupied_count' => max(0, $locked->occupied_count - 1)]);

            $allocation->update(['status' => 'vacated', 'vacated_date' => now()]);
        });
    }
}
