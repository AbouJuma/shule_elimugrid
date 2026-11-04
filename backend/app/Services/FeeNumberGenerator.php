<?php

namespace App\Services;

use App\Models\School;

/**
 * Sequential, per-school numbers for invoices/control numbers/receipts —
 * same locking pattern as AdmissionNumberGenerator (each call must run
 * inside the same DB transaction as the row it's numbering).
 */
class FeeNumberGenerator
{
    public function nextInvoiceNumber(School $school): string
    {
        return $this->next($school, 'next_invoice_sequence', 'INV-%05d');
    }

    /**
     * @deprecated Control numbers now belong to the student, not the invoice
     *             — see nextStudentControlNumber(). Kept only so the old
     *             sequence column isn't orphaned; nothing calls this anymore.
     */
    public function nextControlNumber(School $school): string
    {
        return $this->next($school, 'next_control_number_sequence', 'CN-%08d');
    }

    /**
     * A student's own control number: assigned once, reused on every invoice
     * they ever get — not regenerated per invoice. Numeric, always starting
     * with 8 (spec/user feedback: it should read like the control numbers a
     * parent already recognises, and behave like the student's own permanent
     * reference — "like its primary key").
     */
    public function nextStudentControlNumber(School $school): string
    {
        return $this->next($school, 'next_student_control_sequence', '8%09d');
    }

    public function nextReceiptNumber(School $school): string
    {
        return $this->next($school, 'next_receipt_sequence', 'RCT-%05d');
    }

    protected function next(School $school, string $column, string $format): string
    {
        $locked = School::query()->lockForUpdate()->findOrFail($school->id);

        $sequence = $locked->{$column};
        $locked->{$column} = $sequence + 1;
        $locked->save();

        return sprintf($format, $sequence);
    }
}
