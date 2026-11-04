<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates invoices from a fee structure (spec sections 18-20). Line
 * items are copied onto the invoice at generation time — editing the fee
 * structure afterward never touches invoices already issued from it.
 */
class InvoiceService
{
    public function __construct(protected FeeNumberGenerator $numbers)
    {
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return EloquentCollection<int, Invoice> newly created invoices —
     *                                   students who already have an
     *                                   invoice for this fee structure
     *                                   are skipped, not duplicated.
     */
    public function generate(
        School $school,
        FeeStructure $feeStructure,
        Collection $students,
        ?int $bankAccountId,
        ?\DateTimeInterface $dueDate,
    ): EloquentCollection {
        $feeStructure->loadMissing('items');
        $totalAmount = $feeStructure->items->sum('amount');

        $alreadyInvoiced = Invoice::where('fee_structure_id', $feeStructure->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')
            ->all();

        $created = collect();

        foreach ($students as $student) {
            if (in_array($student->id, $alreadyInvoiced, true)) {
                continue;
            }

            $created->push(DB::transaction(function () use ($school, $feeStructure, $student, $bankAccountId, $dueDate, $totalAmount) {
                // The student's own permanent control number (assigned at admission); a
                // student admitted before this existed gets one now, on first use.
                if (! $student->control_number) {
                    $student->update(['control_number' => $this->numbers->nextStudentControlNumber($school)]);
                }

                $invoice = Invoice::create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'fee_structure_id' => $feeStructure->id,
                    'academic_year_id' => $feeStructure->academic_year_id,
                    'term_id' => $feeStructure->term_id,
                    'bank_account_id' => $bankAccountId,
                    'invoice_number' => $this->numbers->nextInvoiceNumber($school),
                    'control_number' => $student->control_number,
                    'total_amount' => $totalAmount,
                    'amount_paid' => 0,
                    'status' => 'unpaid',
                    'issued_date' => now()->toDateString(),
                    'due_date' => $dueDate?->format('Y-m-d'),
                ]);

                foreach ($feeStructure->items as $item) {
                    $invoice->items()->create(['name' => $item->name, 'amount' => $item->amount]);
                }

                return $invoice;
            }));
        }

        return new EloquentCollection($created->all());
    }
}
