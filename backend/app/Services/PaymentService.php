<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment against an invoice and updates its running balance.
 * The invoice row is locked for the duration of the transaction so two
 * concurrent payments (e.g. cashier + parent portal) can't both read a
 * stale amount_paid and silently lose one payment.
 */
class PaymentService
{
    public function __construct(protected FeeNumberGenerator $numbers)
    {
    }

    /**
     * @param  array{amount: float, method: string, reference?: ?string, notes?: ?string, paid_on: string}  $data
     */
    public function record(Invoice $invoice, array $data, User $recordedBy): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $recordedBy) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $newAmountPaid = bcadd((string) $locked->amount_paid, (string) $data['amount'], 2);

            if (bccomp($newAmountPaid, (string) $locked->total_amount, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => ['This payment would exceed the outstanding balance on the invoice.'],
                ]);
            }

            $payment = Payment::create([
                'school_id' => $locked->school_id,
                'invoice_id' => $locked->id,
                'student_id' => $locked->student_id,
                'receipt_number' => $this->numbers->nextReceiptNumber($locked->school),
                'amount' => $data['amount'],
                'type' => 'payment',
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_on' => $data['paid_on'],
                'recorded_by' => $recordedBy->id,
            ]);

            $locked->update([
                'amount_paid' => $newAmountPaid,
                'status' => bccomp($newAmountPaid, (string) $locked->total_amount, 2) === 0 ? 'paid' : 'partial',
            ]);

            return $payment;
        });
    }

    /**
     * Records a payment that already happened on a payment gateway (real
     * money already moved) — unlike record(), this never rejects for
     * exceeding the balance, since refusing to log money already received
     * would just make it disappear from our books. recorded_by stays null:
     * no staff member recorded this, the gateway's callback did.
     */
    public function recordFromGateway(Invoice $invoice, string $amount, ?string $reference, string $paidOn): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $reference, $paidOn) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $newAmountPaid = bcadd((string) $locked->amount_paid, $amount, 2);

            $payment = Payment::create([
                'school_id' => $locked->school_id,
                'invoice_id' => $locked->id,
                'student_id' => $locked->student_id,
                'receipt_number' => $this->numbers->nextReceiptNumber($locked->school),
                'amount' => $amount,
                'type' => 'payment',
                'method' => 'mobile_money',
                'reference' => $reference,
                'notes' => 'Paid via mobile money checkout.',
                'paid_on' => $paidOn,
                'recorded_by' => null,
            ]);

            $locked->update([
                'amount_paid' => $newAmountPaid,
                'status' => bccomp($newAmountPaid, (string) $locked->total_amount, 2) >= 0 ? 'paid' : 'partial',
            ]);

            return $payment;
        });
    }
}
