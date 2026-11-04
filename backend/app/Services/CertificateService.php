<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issuance/revocation for certificates (spec sections 24-26). Certificates
 * are never deleted or silently edited once issued — revoking sets a
 * status + reason + timestamp, preserving the full audit trail, same
 * principle as payments never being edited in place.
 */
class CertificateService
{
    public function __construct(protected CertificateNumberGenerator $numbers)
    {
    }

    /**
     * @param  array{title: string, achievement?: ?string, issued_date: string, certificate_template_id?: ?int}  $data
     */
    public function issue(School $school, Student $student, array $data, User $issuedBy): Certificate
    {
        return DB::transaction(function () use ($school, $student, $data, $issuedBy) {
            return Certificate::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                // Whichever template the school marked active, unless the caller chose one.
                'certificate_template_id' => $data['certificate_template_id'] ?? CertificateTemplate::query()->where('is_default', true)->value('id'),
                'certificate_number' => $this->numbers->next($school),
                'title' => $data['title'],
                'achievement' => $data['achievement'] ?? null,
                'issued_date' => $data['issued_date'],
                'status' => 'valid',
                'issued_by' => $issuedBy->id,
            ]);
        });
    }

    public function revoke(Certificate $certificate, ?string $reason): Certificate
    {
        if (! $certificate->isValid()) {
            throw ValidationException::withMessages([
                'status' => ['This certificate is already revoked.'],
            ]);
        }

        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        return $certificate;
    }
}
