<?php

namespace App\Services;

use App\Models\School;

/**
 * Same row-locked sequential-number pattern as AdmissionNumberGenerator,
 * FeeNumberGenerator, and CertificateNumberGenerator.
 */
class IdCardNumberGenerator
{
    public function next(School $school): string
    {
        $locked = School::query()->lockForUpdate()->findOrFail($school->id);

        $sequence = $locked->next_id_card_sequence;
        $locked->next_id_card_sequence = $sequence + 1;
        $locked->save();

        return sprintf('ID-%05d', $sequence);
    }
}
