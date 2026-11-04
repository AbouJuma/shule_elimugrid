<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\VirtualClass;
use Illuminate\Validation\ValidationException;

class VirtualClassroomService
{
    public function __construct(protected SmsService $sms)
    {
    }

    public function schedule(School $school, array $data, User $creator): VirtualClass
    {
        return VirtualClass::create([
            'school_id' => $school->id,
            ...$data,
            'created_by' => $creator->id,
            'status' => 'scheduled',
        ]);
    }

    public function cancel(VirtualClass $class): void
    {
        if ($class->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['This session is already cancelled.'],
            ]);
        }

        $class->update(['status' => 'cancelled']);
    }

    /**
     * Reuses SmsService (same "plugs into an existing module" pattern as
     * the Fees module's Send Reminder button) — the join link is the
     * only way a guardian without a portal login finds out about the
     * session at all, since students/guardians don't have portal access.
     *
     * @return array{sent: \Illuminate\Support\Collection, skipped: \Illuminate\Support\Collection}
     */
    public function sendInvites(VirtualClass $class, User $sender): array
    {
        if (! $class->school_class_id) {
            throw ValidationException::withMessages([
                'school_class_id' => ['This session is not linked to a class, so there is no one to invite.'],
            ]);
        }

        $students = Student::where('school_class_id', $class->school_class_id)->where('status', 'active')->get();

        $message = "Dear parent/guardian, \"{$class->title}\" is scheduled for {$class->scheduled_at->format('d M Y, H:i')}. ".
            "Join link: {$class->joinUrl()}";

        return $this->sms->sendToStudents($students, $message, 'general', $sender);
    }
}
