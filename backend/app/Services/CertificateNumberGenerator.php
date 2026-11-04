<?php

namespace App\Services;

use App\Models\School;

/**
 * Same row-locked sequential-number pattern as AdmissionNumberGenerator
 * and FeeNumberGenerator — each call must run inside the same DB
 * transaction as the row it's numbering.
 */
class CertificateNumberGenerator
{
    public function next(School $school): string
    {
        $locked = School::query()->lockForUpdate()->findOrFail($school->id);

        $sequence = $locked->next_certificate_sequence;
        $locked->next_certificate_sequence = $sequence + 1;
        $locked->save();

        return sprintf('CERT-%05d', $sequence);
    }
}
