<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Implements the admission workflow (spec section 14): create the
 * student, generate their admission number, and attach one or more
 * guardians — either linking an existing guardian record (same family,
 * another child already enrolled) or creating a new one inline.
 */
class AdmissionService
{
    public function __construct(
        protected AdmissionNumberGenerator $admissionNumbers,
        protected FeeNumberGenerator $feeNumbers,
    ) {
    }

    /**
     * @param  array{student: array, guardians: array<int, array>}  $data
     */
    public function admit(School $school, array $data): Student
    {
        return DB::transaction(function () use ($school, $data) {
            $student = Student::create([
                ...$data['student'],
                'school_id' => $school->id,
                'admission_number' => $this->admissionNumbers->next($school),
                // A permanent payment reference, assigned once here and reused on
                // every invoice the student ever gets — never regenerated per invoice.
                'control_number' => $this->feeNumbers->nextStudentControlNumber($school),
            ]);

            foreach ($data['guardians'] as $guardianInput) {
                $guardian = $this->resolveGuardian($school, $guardianInput);

                $student->guardians()->attach($guardian->id, [
                    'relationship' => $guardianInput['relationship'] ?? 'guardian',
                    'is_primary' => $guardianInput['is_primary'] ?? false,
                ]);
            }

            return $student->load(['guardians', 'schoolClass', 'stream', 'academicYear']);
        });
    }

    protected function resolveGuardian(School $school, array $input): Guardian
    {
        if (! empty($input['guardian_id'])) {
            return Guardian::where('school_id', $school->id)->findOrFail($input['guardian_id']);
        }

        return Guardian::create([
            'school_id' => $school->id,
            'name' => $input['name'],
            'phone' => $input['phone'],
            'email' => $input['email'] ?? null,
            'occupation' => $input['occupation'] ?? null,
            'address' => $input['address'] ?? null,
            'locale' => $input['locale'] ?? null,
        ]);
    }
}
