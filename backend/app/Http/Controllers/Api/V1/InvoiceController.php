<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\GenerateInvoicesRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\InvoiceService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('fees.view');

        return InvoiceResource::collection(
            Invoice::query()
                ->with(['student', 'bankAccount'])
                ->when($request->integer('student_id'), fn ($q, $id) => $q->where('student_id', $id))
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('fees.view');

        return new InvoiceResource($invoice->load(['student', 'bankAccount', 'items', 'payments']));
    }

    public function generate(
        GenerateInvoicesRequest $request,
        FeeStructure $feeStructure,
        TenantContext $tenant,
        InvoiceService $invoices,
        \App\Services\FeeMessageService $messages,
    ) {
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

        $texted = 0;
        $noPhone = 0;

        if ($request->boolean('notify')) {
            foreach ($created->load('student') as $invoice) {
                try {
                    $messages->forInvoice($invoice, $request->user());
                    $texted++;
                } catch (\Illuminate\Validation\ValidationException) {
                    $noPhone++;
                }
            }
        }

        return response()->json([
            'data' => InvoiceResource::collection($created->load(['student', 'bankAccount', 'items'])),
            'meta' => [
                'generated' => $created->count(),
                'skipped' => $students->count() - $created->count(),
                'texted' => $texted,
                'no_phone' => $noPhone,
            ],
        ], 201);
    }

    /** Text the parent the amount, control number and bank details for this invoice. */
    public function sendSms(Request $request, Invoice $invoice, \App\Services\FeeMessageService $messages)
    {
        $this->authorize('fees.invoice');

        $log = $messages->forInvoice($invoice->load('student'), $request->user(), $request->input('type') === 'reminder' ? 'fee_reminder' : 'fee_invoice');

        return response()->json(['data' => ['queued' => true, 'to' => $log->recipient_name, 'message' => $log->message]]);
    }
}
