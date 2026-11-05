<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use App\Models\PaymentGatewayTransaction;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lets a guardian pay an invoice by mobile-money checkout (AzamPay today)
 * and have the result come back automatically on a callback, instead of a
 * school admin ever having to key in what the parent already paid.
 *
 * A control number alone can't tell us which invoice a payment is for — a
 * student's control number is permanent and shared across every invoice
 * they have (see Invoice::control_number) — so reconciliation is keyed on
 * our own external_id, generated per attempt and tied to one specific
 * invoice from the moment checkout is initiated. The control number still
 * travels with every transaction as the human-readable reference the
 * parent recognises; it's just not the lookup key.
 */
class PaymentGatewayService
{
    public function __construct(
        protected PaymentGateway $gateway,
        protected PaymentService $payments,
        protected FeeMessageService $messages,
    ) {
    }

    /**
     * @param  array{msisdn: string, provider: string, amount?: string}  $data
     */
    public function initiate(Invoice $invoice, array $data): PaymentGatewayTransaction
    {
        $balance = $invoice->balance();

        if (bccomp($balance, '0', 2) <= 0) {
            throw ValidationException::withMessages(['invoice' => ['This invoice is already fully paid.']]);
        }

        $amount = $data['amount'] ?? $balance;

        if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $balance, 2) > 0) {
            throw ValidationException::withMessages(['amount' => ["Amount must be between 0 and the outstanding balance ({$balance})."]]);
        }

        $student = $invoice->student ?? Student::withoutGlobalScopes()->findOrFail($invoice->student_id);
        $externalId = (string) Str::uuid();

        $transaction = PaymentGatewayTransaction::create([
            'school_id' => $invoice->school_id,
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'provider' => $data['provider'],
            'gateway' => 'azampay',
            'external_id' => $externalId,
            'msisdn' => $data['msisdn'],
            'control_number' => $invoice->control_number,
            'amount' => $amount,
            'currency' => $invoice->school?->currency ?? 'TZS',
            'status' => 'initiated',
            'raw_request' => $data,
        ]);

        $result = $this->gateway->checkout(
            $externalId, $data['msisdn'], (string) $amount, $transaction->currency, $data['provider'],
            ['otp' => $data['otp'] ?? null, 'account_number' => $data['account_number'] ?? null, 'account_name' => $data['account_name'] ?? null],
        );

        $transaction->update([
            'status' => $result->status,
            'gateway_transaction_id' => $result->gatewayTransactionId,
            'failure_reason' => $result->error,
            'raw_response' => $result->raw,
        ]);

        if ($result->status === 'completed') {
            $this->settle($transaction->fresh());
        }

        return $transaction->fresh();
    }

    /**
     * The webhook AzamPay calls when a push is approved or declined.
     * `externalreference` is their documented field (an echo of the
     * `externalId`/`referenceId` we sent at checkout — see
     * https://developerdocs.azampay.co.tz/tanzania/checkout-api#callback);
     * the rest are kept as a fallback in case a real callback doesn't
     * match the docs exactly, since that hasn't been observed live yet.
     */
    public function handleCallback(array $payload): void
    {
        $externalId = $payload['externalreference'] ?? $payload['externalId'] ?? $payload['external_id'] ?? $payload['utilityref'] ?? $payload['referenceId'] ?? null;

        if (! $externalId) {
            Log::warning('AzamPay callback with no recognizable external id', ['payload' => $payload]);

            return;
        }

        $transaction = PaymentGatewayTransaction::allTenants()->where('external_id', $externalId)->first();

        if (! $transaction) {
            Log::warning('AzamPay callback for an unknown transaction', ['external_id' => $externalId, 'payload' => $payload]);

            return;
        }

        if (in_array($transaction->status, ['completed', 'failed'], true)) {
            // Already resolved — gateways retry callbacks, so this is
            // expected, not an error.
            return;
        }

        $succeeded = $this->wasSuccessful($payload);
        $transaction->update([
            'raw_callback' => $payload,
            'gateway_transaction_id' => $transaction->gateway_transaction_id
                ?? ($payload['transid'] ?? $payload['transactionId'] ?? $payload['transactionid'] ?? null),
        ]);

        if ($succeeded) {
            $this->settle($transaction->fresh());
        } else {
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => $payload['message'] ?? $payload['transactionstatus'] ?? 'The payment provider reported this as failed.',
            ]);
        }
    }

    protected function wasSuccessful(array $payload): bool
    {
        $status = strtolower((string) ($payload['transactionstatus'] ?? $payload['status'] ?? ''));

        if ($status !== '') {
            return in_array($status, ['success', 'successful', 'completed'], true);
        }

        return ($payload['success'] ?? false) === true;
    }

    /** Turns a completed gateway transaction into a real Payment against its invoice. */
    protected function settle(PaymentGatewayTransaction $transaction): void
    {
        $invoice = Invoice::allTenants()->findOrFail($transaction->invoice_id);

        $payment = $this->payments->recordFromGateway(
            $invoice,
            (string) $transaction->amount,
            $transaction->gateway_transaction_id ?? $transaction->external_id,
            now()->toDateString(),
        );

        $transaction->update(['status' => 'completed', 'payment_id' => $payment->id]);

        try {
            $this->messages->forPayment($payment);
        } catch (ValidationException) {
            // No guardian phone on file — the payment itself already succeeded.
        }
    }
}
