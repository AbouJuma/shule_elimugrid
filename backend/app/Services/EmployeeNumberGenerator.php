<?php

namespace App\Services;

use App\Models\School;

/**
 * Generates sequential, per-school employee numbers (e.g. "EMP-00001").
 * Same row-locked pattern as AdmissionNumberGenerator/FeeNumberGenerator
 * /CertificateNumberGenerator/IdCardNumberGenerator — must run inside the
 * same DB transaction as the staff profile insert it belongs to.
 */
class EmployeeNumberGenerator
{
    public function next(School $school): string
    {
        $locked = School::query()->lockForUpdate()->findOrFail($school->id);

        $sequence = $locked->next_employee_sequence;
        $locked->next_employee_sequence = $sequence + 1;
        $locked->save();

        return sprintf('EMP-%05d', $sequence);
    }
}
