<?php

namespace App\Http\Controllers\Web;

use App\Mail\PayslipMail;
use App\Models\PayrollAdjustment;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\StaffProfile;
use App\Models\StaffSalaryComponent;
use App\Services\PayrollService;
use App\Services\SmsService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class PayrollController extends \App\Http\Controllers\Controller
{
    public const PAYMENT_METHODS = ['bank_transfer', 'mobile_money', 'cash', 'cheque'];

    public function index(Request $request, TenantContext $tenant)
    {
        $this->authorize('payroll.view');
        $tenant->assertBound();

        $tab = in_array($request->string('tab')->toString(), ['components', 'salaries'], true) ? $request->string('tab')->toString() : 'runs';

        $runs = PayrollRun::query()
            ->withCount('payslips')
            ->withSum('payslips as gross_total', 'gross_pay')
            ->withSum('payslips as deductions_total', 'total_deductions')
            ->withSum('payslips as net_total', 'net_pay')
            ->withSum(['payslips as paid_total' => fn ($q) => $q->where('payment_status', 'paid')], 'net_pay')
            ->orderByDesc('period')
            ->paginate(PageSize::resolve($request), ['*'], 'runs_page')
            ->withQueryString();

        $activeStaff = StaffProfile::query()->whereNull('date_left')->with(['user', 'salaryComponents'])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))->get();

        return view('school-admin.payroll.index', [
            'tab' => $tab,
            'runs' => $runs,
            'components' => PayrollComponent::query()->orderBy('type')->orderBy('name')->get(),
            'staff' => $activeStaff->sortBy(fn ($p) => $p->user->name)->values(),
            'stats' => [
                'monthly_bill' => (float) $activeStaff->sum('basic_salary'),
                'staff_count' => $activeStaff->count(),
                'unpaid' => (float) Payslip::query()->where('payment_status', 'pending')
                    ->whereHas('run', fn ($q) => $q->where('status', 'approved'))->sum('net_pay'),
                'paid_this_year' => (float) Payslip::query()->where('payment_status', 'paid')->whereYear('paid_at', now()->year)->sum('net_pay'),
            ],
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function storeRun(Request $request, TenantContext $tenant, PayrollService $payroll)
    {
        $this->authorize('payroll.manage');

        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $result = $payroll->createRun($tenant->assertBound(), $data['period'], $request->user());

        $message = __('Payroll run created with :count payslips.', ['count' => $result['run']->payslips()->count()]);
        if ($result['skipped'] > 0) {
            $message .= ' '.__(':n staff without a basic salary were skipped.', ['n' => $result['skipped']]);
        }

        return redirect()->route('school-admin.payroll.runs.show', $result['run'])->with('status', $message);
    }

    public function showRun(PayrollRun $run)
    {
        $this->authorize('payroll.view');

        $run->load(['payslips.staffProfile.user', 'payslips.lines', 'approver']);

        return view('school-admin.payroll.run', [
            'run' => $run,
            'totals' => [
                'gross' => (float) $run->payslips->sum('gross_pay'),
                'deductions' => (float) $run->payslips->sum('total_deductions'),
                'net' => (float) $run->payslips->sum('net_pay'),
                'paid' => (float) $run->payslips->where('payment_status', 'paid')->sum('net_pay'),
                'pending_count' => $run->payslips->where('payment_status', 'pending')->count(),
            ],
            'methods' => self::PAYMENT_METHODS,
            'employerCost' => (float) $run->payslips->sum('employer_cost'),
            'adjustments' => PayrollAdjustment::query()->with('staffProfile.user')->where('period', $run->period)->latest()->get(),
            'staffOptions' => StaffProfile::query()->with('user')->where('basic_salary', '>', 0)->whereNull('date_left')->get()->sortBy(fn ($p) => $p->user?->name),
        ]);
    }

    public function regenerate(PayrollRun $run, TenantContext $tenant, PayrollService $payroll)
    {
        $this->authorize('payroll.manage');

        $skipped = $payroll->regenerate($tenant->assertBound(), $run);

        return back()->with('status', __('Payslips regenerated.').($skipped ? ' '.__(':n staff without a basic salary were skipped.', ['n' => $skipped]) : ''));
    }

    public function approve(Request $request, PayrollRun $run, PayrollService $payroll)
    {
        $this->authorize('payroll.manage');

        $payroll->approve($run, $request->user());

        return back()->with('status', __('Payroll approved — salaries can now be paid.'));
    }

    public function destroyRun(PayrollRun $run, PayrollService $payroll)
    {
        $this->authorize('payroll.manage');

        $payroll->deleteDraft($run);

        return redirect()->route('school-admin.payroll.index')->with('status', __('Draft payroll run deleted.'));
    }

    public function disburse(Request $request, Payslip $payslip, PayrollService $payroll)
    {
        $this->authorize('payroll.disburse');

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payroll->disburse($payslip, $data['payment_method'], $data['payment_reference'] ?? null, $request->user());

        return back()->with('status', __('Salary marked as paid.'));
    }

    public function disburseAll(Request $request, PayrollRun $run, PayrollService $payroll)
    {
        $this->authorize('payroll.disburse');

        $data = $request->validate(['payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)]]);

        $count = $payroll->disburseAll($run, $data['payment_method'], $request->user());

        return back()->with('status', __(':n salaries marked as paid.', ['n' => $count]));
    }

    /** A bank/mobile-money payment sheet for the run — what finance uploads or works from when paying out. */
    public function export(PayrollRun $run)
    {
        $this->authorize('payroll.view');

        $run->load('payslips.staffProfile.user');

        return response()->streamDownload(function () use ($run) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee No.', 'Name', 'Bank', 'Account No.', 'Net Pay', 'Status', 'Method', 'Reference']);

            foreach ($run->payslips as $payslip) {
                $profile = $payslip->staffProfile;
                fputcsv($out, [
                    $profile->employee_number, $profile->user->name, $profile->bank_name, $profile->bank_account_number,
                    $payslip->net_pay, $payslip->payment_status, $payslip->payment_method, $payslip->payment_reference,
                ]);
            }

            fclose($out);
        }, "payroll-{$run->period}.csv", ['Content-Type' => 'text/csv']);
    }

    public function payslipPdf(Request $request, Payslip $payslip, TenantContext $tenant)
    {
        $payslip->load(['run', 'staffProfile.user', 'lines']);

        // Payroll staff can open any payslip; everyone else only their own (once approved).
        if (! $request->user()->can('payroll.view')) {
            abort_unless(\App\Http\Controllers\Api\V1\PayslipController::ownedBy($request->user(), $payslip), 403);
        }


        return Pdf::loadView('payroll.payslip', ['payslip' => $payslip, 'school' => $tenant->assertBound()])
            ->setPaper('a4', 'portrait')
            ->stream("payslip-{$payslip->staffProfile->employee_number}-{$payslip->run->period}.pdf");
    }

    // ---- Components -------------------------------------------------------------------------

    public function storeComponent(Request $request, TenantContext $tenant)
    {
        $this->authorize('payroll.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $this->validateComponent($request, $schoolId);
        PayrollComponent::create(['school_id' => $schoolId, ...$data, 'apply_to_all' => $request->boolean('apply_to_all')]);

        return redirect()->route('school-admin.payroll.index', ['tab' => 'components'])->with('status', __('Component added.'));
    }

    public function updateComponent(Request $request, PayrollComponent $component, TenantContext $tenant)
    {
        $this->authorize('payroll.manage');

        $data = $this->validateComponent($request, $tenant->assertBound()->id, $component->id);
        $component->update([...$data, 'apply_to_all' => $request->boolean('apply_to_all')]);

        return redirect()->route('school-admin.payroll.index', ['tab' => 'components'])->with('status', __('Component updated.'));
    }

    public function destroyComponent(PayrollComponent $component)
    {
        $this->authorize('payroll.manage');

        // Past payslips keep their own copied lines, so history is untouched.
        DB::transaction(function () use ($component) {
            StaffSalaryComponent::where('payroll_component_id', $component->id)->delete();
            $component->forceDelete();
        });

        return redirect()->route('school-admin.payroll.index', ['tab' => 'components'])->with('status', __('Component deleted.'));
    }

    protected function validateComponent(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('payroll_components', 'name')->where('school_id', $schoolId)->ignore($ignoreId)],
            'type' => ['required', Rule::in(['allowance', 'deduction'])],
            'calculation' => ['required', Rule::in(['fixed', 'percent'])],
            'base' => ['nullable', Rule::in(PayrollComponent::BASES)],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'bands_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $bands = $this->parseBands($data['bands_text'] ?? null);
        unset($data['bands_text']);

        if ($bands === null && $data['calculation'] === 'percent' && $data['default_amount'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['default_amount' => [__('A percentage cannot exceed 100.')]]);
        }

        $isDeduction = $data['type'] === 'deduction';

        return [
            ...$data,
            'base' => $data['base'] ?? 'basic',
            'bands' => $isDeduction ? $bands : null,
            'pre_tax' => $isDeduction && $request->boolean('pre_tax'),
            'employer_paid' => $isDeduction && $request->boolean('employer_paid'),
        ];
    }

    /**
     * "270000=0" per line = up to that income taxed at that %, the last line
     * "above=30" (or "*=30") = everything over the previous limit. Blank = no bands.
     *
     * @return array<int, array{upto: ?int, rate: float}>|null
     */
    protected function parseBands(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $bands = [];
        $previous = 0;

        foreach (preg_split('/\R/', $text) as $i => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! preg_match('/^(\d+(?:\.\d+)?|above|\*)\s*=\s*(\d+(?:\.\d+)?)$/i', $line, $m) || (float) $m[2] > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages(['bands_text' => [__('Tax bands: one per line like 270000=0, ending with above=30 (rates are percentages up to 100). Problem on line :n.', ['n' => $i + 1])]]);
            }

            $open = ! is_numeric($m[1]);

            if (! $open && (float) $m[1] <= $previous) {
                throw \Illuminate\Validation\ValidationException::withMessages(['bands_text' => [__('Band limits must increase from one line to the next (line :n).', ['n' => $i + 1])]]);
            }

            $bands[] = ['upto' => $open ? null : (float) $m[1] + 0, 'rate' => (float) $m[2]];
            $previous = $open ? $previous : (float) $m[1];

            if ($open) {
                break;
            }
        }

        return $bands ?: null;
    }

    public function installStatutory(TenantContext $tenant, PayrollService $payroll)
    {
        $this->authorize('payroll.manage');

        $created = $payroll->installTanzaniaStatutory($tenant->assertBound());

        return redirect()->route('school-admin.payroll.index', ['tab' => 'components'])
            ->with('status', $created > 0
                ? __(':n statutory components added. Check every rate against the current TRA / NSSF / WCF rules before running payroll.', ['n' => $created])
                : __('The statutory components are already set up.'));
    }

    // ---- One-off adjustments (arrears, bonuses, recoveries) ----------------------------------------

    public function storeAdjustment(Request $request, TenantContext $tenant)
    {
        $this->authorize('payroll.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $request->validate([
            'staff_profile_id' => ['required', Rule::exists('staff_profiles', 'id')->where('school_id', $schoolId)],
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['allowance', 'deduction'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Only a run that's still a draft (or not yet created) can take new adjustments.
        $run = PayrollRun::query()->where('school_id', $schoolId)->where('period', $data['period'])->first();

        if ($run && ! $run->isDraft()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['period' => [__('That month\'s payroll is already approved — adjustments can only be added before approval.')]]);
        }

        PayrollAdjustment::create([...$data, 'school_id' => $schoolId, 'created_by' => $request->user()->id]);

        return back()->with('status', __('Adjustment saved. Regenerate the draft payroll to include it.'));
    }

    public function destroyAdjustment(PayrollAdjustment $adjustment)
    {
        $this->authorize('payroll.manage');

        $run = PayrollRun::query()->where('period', $adjustment->period)->first();

        if ($run && ! $run->isDraft()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['period' => [__('That month\'s payroll is already approved — adjustments can no longer be removed.')]]);
        }

        $adjustment->delete();

        return back()->with('status', __('Adjustment removed. Regenerate the draft payroll to apply the change.'));
    }

    // ---- Payslip SMS ---------------------------------------------------------------------------------------

    /** A short "your salary is ready" text to every staff member who has a phone number. */
    public function smsPayslips(Request $request, PayrollRun $run, TenantContext $tenant, SmsService $sms)
    {
        $this->authorize('payroll.manage');

        if ($run->isDraft()) {
            return back()->withErrors(['run' => __('Approve the payroll run before sending payslips.')]);
        }

        $school = $tenant->assertBound();
        $cur = $school->currency ?? 'TZS';
        $sent = 0;
        $skipped = 0;

        foreach ($run->payslips()->with(['staffProfile.user'])->get() as $payslip) {
            $user = $payslip->staffProfile?->user;

            if (! $user?->phone) {
                $skipped++;

                continue;
            }

            $paid = $payslip->isPaid() ? __('paid') : __('approved');
            $sms->sendToPhone($school->id, $user->phone, $user->name, __('Payslip :period: net pay :cur :net (:status). — :school', [
                'period' => $run->periodLabel(), 'cur' => $cur, 'net' => number_format((float) $payslip->net_pay), 'status' => $paid, 'school' => $school->name,
            ]), 'payslip', $request->user());
            $sent++;
        }

        return back()->with('status', __(':sent payslip SMS queued.', ['sent' => $sent]).($skipped ? ' '.__(':n staff have no phone number.', ['n' => $skipped]) : ''));
    }

    // ---- Payslip e-mail -------------------------------------------------------------------------

    public function emailPayslips(PayrollRun $run, TenantContext $tenant)
    {
        $this->authorize('payroll.manage');

        if ($run->isDraft()) {
            return back()->withErrors(['run' => __('Approve the payroll run before sending payslips.')]);
        }

        $school = $tenant->assertBound();
        $sent = 0;
        $skipped = 0;

        foreach ($run->payslips()->with(['run', 'staffProfile.user', 'lines'])->get() as $payslip) {
            $email = $payslip->staffProfile?->user?->email;

            if (! $email) {
                $skipped++;

                continue;
            }

            Mail::to($email)->send(new PayslipMail($payslip, $school));
            $sent++;
        }

        return back()->with('status', __(':sent payslips e-mailed.', ['sent' => $sent]).($skipped ? ' '.__(':n staff have no e-mail address.', ['n' => $skipped]) : ''));
    }

    // ---- Staff salary set-up ------------------------------------------------------------------

    public function updateSalary(Request $request, StaffProfile $staffProfile, TenantContext $tenant)
    {
        $this->authorize('payroll.manage');
        $schoolId = $tenant->assertBound()->id;

        $data = $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'components' => ['nullable', 'array'],
            'components.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validIds = PayrollComponent::query()->where('school_id', $schoolId)->pluck('id')->all();

        DB::transaction(function () use ($data, $staffProfile, $validIds) {
            $staffProfile->update([
                'basic_salary' => $data['basic_salary'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
            ]);

            $staffProfile->salaryComponents()->delete();

            foreach ($data['components'] ?? [] as $componentId => $amount) {
                if ($amount === null || $amount === '' || ! in_array((int) $componentId, $validIds, true)) {
                    continue;
                }

                $staffProfile->salaryComponents()->create(['payroll_component_id' => $componentId, 'amount' => $amount]);
            }
        });

        return redirect()->route('school-admin.payroll.index', ['tab' => 'salaries'])
            ->with('status', __('Salary details saved for :name.', ['name' => $staffProfile->user->name]));
    }
}
