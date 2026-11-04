<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Fees\GenerateInvoicesRequest;
use App\Http\Requests\Fees\RecordPaymentRequest;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\SmsService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('fees.view');

        return view('school-admin.invoices.index', [
            'invoices' => Invoice::query()
                ->with('student')
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('fees.view');

        return view('school-admin.invoices.show', [
            'invoice' => $invoice->load(['student', 'bankAccount', 'items', 'payments' => fn ($q) => $q->latest()]),
        ]);
    }

    public function generateForStructure(GenerateInvoicesRequest $request, FeeStructure $feeStructure, TenantContext $tenant, InvoiceService $invoices, \App\Services\FeeMessageService $messages)
    {
        $data = $request->validated();

        $students = isset($data['student_ids'])
            ? Student::whereIn('id', $data['student_ids'])->get()
            : Student::where('school_class_id', $feeStructure->school_class_id)->where('status', 'active')->get();

        $created = $invoices->generate(
            $tenant->assertBound(),
            $feeStructure,
            $students,
            $data['bank_account_id'] ?? null,
            isset($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
        );

        $skipped = $students->count() - $created->count();
        $message = __(':n invoice(s) generated.', ['n' => $created->count()]).($skipped > 0 ? ' '.__(':n student(s) already had one.', ['n' => $skipped]) : '');

        if ($request->boolean('notify')) {
            $texted = 0;
            $failed = 0;
            foreach ($created->load('student') as $invoice) {
                try {
                    $log = $messages->forInvoice($invoice, $request->user());
                    $log->status === 'failed' ? $failed++ : $texted++;
                } catch (\Illuminate\Validation\ValidationException) {
                }
            }
            $message .= ' '.__(':n parent(s) were sent the fees to pay.', ['n' => $texted]);
            if ($failed > 0) {
                $message .= ' '.__(':n message(s) failed to send.', ['n' => $failed]);
            }
        }

        return redirect()->route('school-admin.invoices.index')->with('status', $message);
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice, PaymentService $payments, \App\Services\FeeMessageService $messages)
    {
        $payment = $payments->record($invoice, $request->validated(), $request->user());
        $status = __('Payment recorded.');

        try {
            $log = $messages->forPayment($payment, $request->user());
            if ($log->status === 'failed') {
                $status .= ' '.__('The confirmation message failed to send.');
            }
        } catch (ValidationException) {
            $status .= ' '.__('The parent was not texted — no guardian phone number on file.');
        }

        return redirect()->route('school-admin.invoices.show', $invoice)->with('status', $status);
    }

    public function sendReminder(Request $request, Invoice $invoice, \App\Services\FeeMessageService $messages)
    {
        $this->authorize('communications.send');

        try {
            $log = $messages->forInvoice($invoice->load('student'), $request->user(), 'fee_reminder');
        } catch (ValidationException $e) {
            return redirect()->route('school-admin.invoices.show', $invoice)
                ->with('status', $e->validator->errors()->first());
        }

        return redirect()->route('school-admin.invoices.show', $invoice)->with(
            'status',
            $log->status === 'failed' ? __('The reminder failed to send.') : __('Reminder sent to the guardian.')
        );
    }
}
