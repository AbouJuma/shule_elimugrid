<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential, per-school admission numbers (e.g. "STU-00001").
 * Must be called inside the same DB transaction as the student insert it
 * belongs to — it locks the school row for the duration of the
 * transaction so concurrent admissions can never be handed the same
 * number, then persists the incremented counter immediately.
 */
class AdmissionNumberGenerator
{
    public function next(School $school): string
    {
        $locked = School::query()->lockForUpdate()->findOrFail($school->id);

        $sequence = $locked->next_admission_sequence;
        $locked->next_admission_sequence = $sequence + 1;
        $locked->save();

        return sprintf('STU-%05d', $sequence);
    }
}
