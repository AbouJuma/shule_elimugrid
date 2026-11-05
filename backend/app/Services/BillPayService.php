<?php

namespace App\Services;

use App\Models\BillPayPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AzamPay's Bill Pay product: a guardian pays by entering the student's
 * control number into *any* bank's or mobile network's own "pay bill"
 * menu — no app, no AzamPay account needed on their side at all. AzamPay
 * (acting on the bank/network's behalf) calls us to look up the name and
 * amount owed, then again to confirm the payment.
 *
 * A control number isn't tied to one invoice (it's permanent per student —
 * see Invoice::control_number), so a single lump sum here can cover
 * several open invoices at once. This applies it oldest-invoice-first
 * (FIFO) until the money runs out, exactly matching how a parent reading
 * "you owe X in total" would expect a part-payment to be used.
 */
class BillPayService
{
    public function __construct(
        protected PaymentService $payments,
        protected FeeMessageService $messages,
    ) {
    }

    /** What AzamPay's name-lookup call needs: who this control number belongs to and what they currently owe in total. */
    public function lookup(string $billIdentifier): ?array
    {
        $student = Student::allTenants()->where('control_number', $billIdentifier)->first();

        if (! $student) {
            return null;
        }

        return ['student' => $student, 'balance' => $this->totalBalance($student)];
    }

    /**
     * Applies one incoming lump-sum payment. Idempotent on fsp_reference_id
     * — AzamPay (or the bank behind it) may retry this call, and retrying
     * must not apply the same money twice.
     *
     * @param  array<string, mixed>  $raw
     *
     * @throws ValidationException if the control number doesn't match any student.
     */
    public function pay(string $billIdentifier, string $amount, string $fspReferenceId, ?string $pgReferenceId, ?string $fspCode, ?string $billType, array $raw): BillPayPayment
    {
        if ($existing = BillPayPayment::allTenants()->where('fsp_reference_id', $fspReferenceId)->first()) {
            return $existing;
        }

        $student = Student::allTenants()->where('control_number', $billIdentifier)->first();

        if (! $student) {
            throw ValidationException::withMessages(['bill_identifier' => ["No student found for control number {$billIdentifier}."]]);
        }

        $isBank = in_array($fspCode, ['CRDB', 'NMB'], true);

        $billPayPayment = BillPayPayment::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'bill_identifier' => $billIdentifier,
            'fsp_reference_id' => $fspReferenceId,
            'pg_reference_id' => $pgReferenceId,
            'merchant_reference_id' => (string) Str::uuid(),
            'amount' => $amount,
            'fsp_code' => $fspCode,
            'bill_type' => $billType,
            'status' => 'completed',
            'raw_request' => $raw,
        ]);

        $this->applyFifo($student, $amount, $billPayPayment, $isBank ? 'bank_transfer' : 'mobile_money');

        return $billPayPayment;
    }

    protected function totalBalance(Student $student): string
    {
        return $this->openInvoices($student)->reduce(fn ($carry, Invoice $invoice) => bcadd($carry, $invoice->balance(), 2), '0.00');
    }

    /** @return \Illuminate\Support\Collection<int, Invoice> oldest first. */
    protected function openInvoices(Student $student)
    {
        return Invoice::allTenants()->where('student_id', $student->id)
            ->whereColumn('amount_paid', '<', 'total_amount')
            ->orderBy('issued_date')->orderBy('id')->get();
    }

    protected function applyFifo(Student $student, string $amount, BillPayPayment $billPayPayment, string $method): void
    {
        $remaining = $amount;
        $notes = 'Paid via AzamPay Bill Pay (control number '.$billPayPayment->bill_identifier.').';
        $lastPayment = null;

        foreach ($this->openInvoices($student) as $invoice) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $balance = $invoice->balance();
            $applyAmount = bccomp($remaining, $balance, 2) < 0 ? $remaining : $balance;

            $lastPayment = $this->payments->recordFromGateway(
                $invoice, $applyAmount, $billPayPayment->merchant_reference_id, now()->toDateString(), $method, $notes, $billPayPayment->id,
            );
            $this->notify($lastPayment);

            $remaining = bcsub($remaining, $applyAmount, 2);
        }

        if (bccomp($remaining, '0', 2) > 0) {
            if ($lastPayment) {
                // Paid more than everything owed — keep the extra in the
                // books against the last invoice touched (as an overpayment)
                // rather than silently lose received money.
                $extra = $this->payments->recordFromGateway(
                    $lastPayment->invoice, $remaining, $billPayPayment->merchant_reference_id, now()->toDateString(),
                    $method, $notes.' (overpayment — exceeds total owed)', $billPayPayment->id,
                );
                $this->notify($extra);
            } else {
                Log::warning('Bill Pay payment received for a student with no open invoices', [
                    'student_id' => $student->id, 'bill_identifier' => $billPayPayment->bill_identifier, 'amount' => $amount,
                ]);
            }
        }
    }

    protected function notify(Payment $payment): void
    {
        try {
            $this->messages->forPayment($payment);
        } catch (ValidationException) {
            // No guardian phone on file — the payment itself already succeeded.
        }
    }
}
