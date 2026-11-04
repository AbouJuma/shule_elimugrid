<?php

namespace App\Services;

use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issuance/revocation for student ID cards (spec sections 22-23). A
 * student normally has exactly one active card at a time — issuing a
 * new one (lost/damaged replacement) automatically supersedes whichever
 * one was active, rather than requiring the caller to revoke it first.
 * Old cards are never deleted, so a lost card's history stays auditable.
 */
class IdCardService
{
    public function __construct(protected IdCardNumberGenerator $numbers)
    {
    }

    /**
     * @param  array{id_card_template_id?: ?int, issued_date: string}  $data
     */
    public function issue(School $school, Student $student, array $data, User $issuedBy): IdCard
    {
        return DB::transaction(function () use ($school, $student, $data, $issuedBy) {
            IdCard::where('student_id', $student->id)
                ->where('status', 'active')
                ->update(['status' => 'superseded']);

            return IdCard::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                // Whichever template the school marked active, unless the caller chose one.
                'id_card_template_id' => $data['id_card_template_id'] ?? IdCardTemplate::query()->where('is_default', true)->value('id'),
                'card_number' => $this->numbers->next($school),
                'status' => 'active',
                'issued_date' => $data['issued_date'],
                'issued_by' => $issuedBy->id,
            ]);
        });
    }

    public function revoke(IdCard $idCard, ?string $reason): IdCard
    {
        if (! $idCard->isActive()) {
            throw ValidationException::withMessages([
                'status' => ['Only an active card can be revoked.'],
            ]);
        }

        $idCard->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);

        return $idCard;
    }
}
