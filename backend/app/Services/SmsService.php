<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\PhoneNumberNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SmsService
{
    /**
     * Sends to a single student's primary guardian (falling back to any
     * guardian if none is flagged primary). Throws a validation error
     * rather than silently skipping — a school admin composing a
     * reminder needs to know it didn't go out, not discover it later.
     */
    public function sendToStudent(Student $student, string $message, string $category, ?User $sender = null): SmsLog
    {
        $guardian = $student->guardians()->wherePivot('is_primary', true)->first()
            ?? $student->guardians()->first();

        if (! $guardian || ! $guardian->phone) {
            throw ValidationException::withMessages([
                'student_id' => ["{$student->fullName()} has no guardian phone number on file."],
            ]);
        }

        return $this->dispatch(
            schoolId: $student->school_id,
            phone: $guardian->phone,
            recipientName: $guardian->name,
            message: $message,
            category: $category,
            student: $student,
            sender: $sender,
        );
    }

    /**
     * Sends the same message to every student in $students that has a
     * reachable guardian; students without one are skipped (returned
     * separately) rather than failing the whole batch.
     *
     * @return array{sent: Collection<int, SmsLog>, skipped: Collection<int, Student>}
     */
    public function sendToStudents(Collection $students, string $message, string $category, ?User $sender = null): array
    {
        $sent = collect();
        $skipped = collect();

        foreach ($students as $student) {
            try {
                $sent->push($this->sendToStudent($student, $message, $category, $sender));
            } catch (ValidationException) {
                $skipped->push($student);
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Sends to a phone number that isn't a student's guardian — a staff
     * member's payslip notice, for instance. Same log + queue as every
     * other message, so it appears in the SMS history and retries the same way.
     */
    public function sendToPhone(int $schoolId, string $phone, ?string $recipientName, string $message, string $category, ?User $sender = null, ?Student $student = null): SmsLog
    {
        return $this->dispatch($schoolId, $phone, $recipientName, $message, $category, $student, $sender);
    }

    protected function dispatch(
        int $schoolId,
        string $phone,
        ?string $recipientName,
        string $message,
        string $category,
        ?Student $student,
        ?User $sender,
    ): SmsLog {
        $log = SmsLog::create([
            'school_id' => $schoolId,
            'student_id' => $student?->id,
            'sent_by' => $sender?->id,
            'recipient_phone' => PhoneNumberNormalizer::normalize($phone, config('services.sms.default_country_code')),
            'recipient_name' => $recipientName,
            'category' => $category,
            'message' => $message,
            'status' => 'pending',
        ]);

        SendSmsJob::dispatch($log);

        return $log;
    }
}
