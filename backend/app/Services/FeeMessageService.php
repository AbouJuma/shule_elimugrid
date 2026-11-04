<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Tells a parent what to pay: the amount, the control number and the school's
 * bank details, in their language, worded by the school's own (editable)
 * templates. Used when a student is admitted, when an invoice is issued to an
 * enrolled student, and when the school resends one.
 */
class FeeMessageService
{
    public function __construct(
        protected InvoiceService $invoices,
        protected MessageTemplateService $templates,
        protected SmsService $sms,
    ) {
    }

    /**
     * Invoices the newly admitted student from their class's fee structure and
     * texts their parent. Never throws for a missing piece — the admission has
     * already succeeded — but says what was skipped and why.
     *
     * @return array{status: 'sent'|'failed'|'skipped', reason: ?string, invoice: ?Invoice, sms: ?SmsLog}
     */
    public function onAdmission(Student $student, ?User $by = null): array
    {
        $school = School::findOrFail($student->school_id);

        if (! $student->school_class_id) {
            return $this->skipped('no_class');
        }

        $guardian = $this->contactFor($student);

        if (! $guardian) {
            return $this->skipped('no_guardian_phone');
        }

        $structure = $this->structureFor($student);

        if (! $structure) {
            return $this->skipped('no_fee_structure');
        }

        $invoice = $this->invoices->generate($school, $structure, collect([$student]), $this->defaultBankAccount($school)?->id, null)->first()
            ?? Invoice::where('student_id', $student->id)->where('fee_structure_id', $structure->id)->first();

        $log = $this->send($invoice, $student, $guardian, 'admission_fees', $by);

        return [
            'status' => $log->status === 'failed' ? 'failed' : 'sent',
            'reason' => $log->status === 'failed' ? ($log->error ?? 'gateway_error') : null,
            'invoice' => $invoice,
            'sms' => $log,
        ];
    }

    /** Texts the parent about an existing invoice (resend, or after invoices are generated for a class). */
    public function forInvoice(Invoice $invoice, ?User $by = null, string $event = 'fee_invoice'): SmsLog
    {
        $student = $invoice->student ?? Student::withoutGlobalScopes()->findOrFail($invoice->student_id);
        $guardian = $this->contactFor($student);

        if (! $guardian) {
            throw ValidationException::withMessages([
                'student_id' => [__(':name has no guardian phone number on file.', ['name' => $student->fullName()])],
            ]);
        }

        return $this->send($invoice, $student, $guardian, $event, $by);
    }

    /**
     * Texts the parent what they just paid and what's left, every time a
     * payment (full or partial) is recorded. Reloads the invoice fresh so
     * the balance reflects this payment, not a stale copy from before it.
     */
    public function forPayment(Payment $payment, ?User $by = null): SmsLog
    {
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($payment->invoice_id);
        $student = $invoice->student ?? Student::withoutGlobalScopes()->findOrFail($invoice->student_id);
        $guardian = $this->contactFor($student);

        if (! $guardian) {
            throw ValidationException::withMessages([
                'student_id' => [__(':name has no guardian phone number on file.', ['name' => $student->fullName()])],
            ]);
        }

        $school = School::findOrFail($student->school_id);
        $locale = $this->templates->localeFor($school, $guardian);
        $data = $this->data($invoice, $student, $guardian, $locale);
        $data['amount_paid'] = $this->money($payment->amount);
        $data['receipt_number'] = (string) $payment->receipt_number;

        $text = $this->templates->render($school, 'payment_received', $locale, $data);

        return $this->sms->sendToPhone($school->id, $guardian->phone, $guardian->name, $text, 'fees', $by, $student);
    }

    /** What the message will say, without sending it (for the template preview). */
    public function preview(School $school, string $event, string $locale, ?Invoice $invoice = null): string
    {
        $data = $invoice
            ? $this->data($invoice, $invoice->student, $this->contactFor($invoice->student), $locale)
            : $this->sampleData($school, $locale);

        return $this->templates->render($school, $event, $locale, $data);
    }

    protected function send(Invoice $invoice, Student $student, Guardian $guardian, string $event, ?User $by): SmsLog
    {
        $school = School::findOrFail($student->school_id);
        $locale = $this->templates->localeFor($school, $guardian);
        $text = $this->templates->render($school, $event, $locale, $this->data($invoice, $student, $guardian, $locale));

        return $this->sms->sendToPhone($school->id, $guardian->phone, $guardian->name, $text, 'fees', $by, $student);
    }

    /** The primary guardian (or any) who has a phone number. */
    protected function contactFor(Student $student): ?Guardian
    {
        $guardians = $student->guardians()->get()->filter(fn ($g) => filled($g->phone));

        return $guardians->first(fn ($g) => (bool) $g->pivot->is_primary) ?? $guardians->first();
    }

    protected function structureFor(Student $student): ?FeeStructure
    {
        return FeeStructure::withoutGlobalScopes()
            ->where('school_id', $student->school_id)
            ->where('school_class_id', $student->school_class_id)
            ->where('academic_year_id', $student->academic_year_id)
            ->with(['term', 'items'])
            ->orderBy('id')
            ->get()
            ->sortBy(fn ($s) => $s->term?->is_current ? 0 : 1)
            ->first();
    }

    protected function defaultBankAccount(School $school): ?BankAccount
    {
        return BankAccount::withoutGlobalScopes()
            ->where('school_id', $school->id)->where('is_active', true)
            ->orderByRaw("CASE WHEN LOWER(COALESCE(purpose, '')) LIKE '%fee%' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    /** @return array<string, string> */
    protected function data(Invoice $invoice, Student $student, ?Guardian $guardian, string $locale): array
    {
        $invoice->loadMissing(['term', 'academicYear', 'bankAccount']);
        $school = School::findOrFail($invoice->school_id);
        $student->loadMissing('schoolClass');

        return [
            'guardian_name' => $guardian?->name ?? '',
            'student_name' => $student->fullName(),
            'admission_number' => $student->admission_number,
            'class' => $student->schoolClass?->name ?? '',
            'school_name' => $school->name,
            'term' => $invoice->term?->name ?? $invoice->academicYear?->name ?? '',
            'currency' => $school->currency ?? '',
            'amount' => $this->money($invoice->total_amount),
            'balance' => $this->money($invoice->balance()),
            'control_number' => (string) $invoice->control_number,
            'invoice_number' => (string) $invoice->invoice_number,
            'bank_details' => $this->bankDetails($school, $invoice->bankAccount, $locale),
            'due_line' => $invoice->due_date
                ? __('Pay by :date.', ['date' => $invoice->due_date->copy()->locale($locale)->translatedFormat('j F Y')], $locale).' '
                : '',
        ];
    }

    /** @return array<string, string> */
    protected function sampleData(School $school, string $locale): array
    {
        return [
            'guardian_name' => 'Asha Kileo', 'student_name' => 'Neema Kileo', 'admission_number' => 'STU-00042', 'class' => 'Form 1',
            'school_name' => $school->name, 'term' => 'Term 1', 'currency' => $school->currency ?? 'TZS', 'amount' => '850,000', 'balance' => '850,000',
            'control_number' => '8000000042', 'invoice_number' => 'INV-00042',
            'bank_details' => $this->bankDetails($school, null, $locale) ?: __('Bank', [], $locale).': CRDB Bank, '.__('Account name', [], $locale).': '.$school->name.', '.__('Account number', [], $locale).': 0150000000.',
            'due_line' => __('Pay by :date.', ['date' => now()->addDays(14)->locale($locale)->translatedFormat('j F Y')], $locale).' ',
            'email' => 'asha@example.com', 'password' => 'Zx81QwErTy55',
            'amount_paid' => '200,000', 'receipt_number' => 'RCT-00042',
        ];
    }

    /** "Bank: CRDB, Account name: X, Account number: 123." — the invoice's account, or up to three active ones. */
    protected function bankDetails(School $school, ?BankAccount $preferred, string $locale): string
    {
        $accounts = $preferred
            ? collect([$preferred])
            : BankAccount::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->orderBy('id')->limit(3)->get();

        return $accounts->map(fn ($a) => __('Bank', [], $locale).': '.$a->bank_name.', '.__('Account name', [], $locale).': '.$a->account_name.', '.__('Account number', [], $locale).': '.$a->account_number.'.')->implode(' ');
    }

    protected function money(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    }

    /** @return array{status: 'skipped', reason: string, invoice: null, sms: null} */
    protected function skipped(string $reason): array
    {
        return ['status' => 'skipped', 'reason' => $reason, 'invoice' => null, 'sms' => null];
    }
}
