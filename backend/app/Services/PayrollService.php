<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly payroll: build → (regenerate while draft) → approve → disburse.
 *
 * Pay for an employee = basic salary + allowances − deductions. A component
 * is a fixed amount, a percentage (of basic, gross or taxable income) or a
 * progressive tax-band table; deductions run pre-tax first, then tax;
 * employer-paid components are a school cost that never reduces net pay.
 * A component flagged apply_to_all reaches every employee at its default
 * amount unless the employee has their own override; other components
 * only apply where they've been assigned. Disbursement is row-locked so a
 * payslip can never be paid twice by two people clicking at once.
 * Every query filters by school_id explicitly (safe outside HTTP requests).
 */
class PayrollService
{
    /** Working days (Mon–Sat) a month's basic salary is spread over when pricing unpaid leave. */
    public const WORKING_DAYS_PER_MONTH = 26;

    /** @return array{run: PayrollRun, skipped: int} */
    public function createRun(School $school, string $period, User $creator): array
    {
        if (PayrollRun::withoutGlobalScopes()->where('school_id', $school->id)->where('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => [__('A payroll run already exists for :period.', ['period' => $period])]]);
        }

        return DB::transaction(function () use ($school, $period, $creator) {
            $run = PayrollRun::withoutGlobalScopes()->create([
                'school_id' => $school->id, 'period' => $period, 'status' => 'draft', 'created_by' => $creator->id,
            ]);

            return ['run' => $run, 'skipped' => $this->generatePayslips($school, $run)];
        });
    }

    /** Rebuilds a draft run's payslips from the current salary set-up. */
    public function regenerate(School $school, PayrollRun $run): int
    {
        $this->assertDraft($run);

        return DB::transaction(function () use ($school, $run) {
            Payslip::withoutGlobalScopes()->where('payroll_run_id', $run->id)->delete();

            return $this->generatePayslips($school, $run);
        });
    }

    /** @return int number of active staff skipped because they have no basic salary set */
    protected function generatePayslips(School $school, PayrollRun $run): int
    {
        $components = PayrollComponent::withoutGlobalScopes()->where('school_id', $school->id)->get();
        $adjustments = PayrollAdjustment::withoutGlobalScopes()
            ->where('school_id', $school->id)->where('period', $run->period)->get()->groupBy('staff_profile_id');

        $staff = StaffProfile::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereNull('date_left')
            ->whereHas('user', fn ($q) => $q->withoutGlobalScopes()->where('status', 'active'))
            ->with('salaryComponents')
            ->get();

        $skipped = 0;

        foreach ($staff as $profile) {
            $basic = (float) $profile->basic_salary;

            if ($basic <= 0) {
                $skipped++;

                continue;
            }

            $overrides = $profile->salaryComponents->pluck('amount', 'payroll_component_id');

            // Components that reach this person (assigned, or apply to everyone).
            $mine = $components->filter(fn ($c) => $overrides->has($c->id) || $c->apply_to_all);
            $override = fn (PayrollComponent $c) => $overrides->has($c->id) ? (float) $overrides[$c->id] : null;
            $extras = $adjustments->get($profile->id, collect());

            $lines = [];

            // 1. Earnings: allowances, then this month's one-off additions.
            foreach ($mine->where('type', 'allowance') as $c) {
                $amount = $c->amountFor($basic, $override($c));

                if ($amount > 0) {
                    $lines[] = ['name' => $c->name, 'type' => 'allowance', 'amount' => $amount, 'is_employer' => false];
                }
            }

            foreach ($extras->where('type', 'allowance') as $adj) {
                $lines[] = ['name' => $adj->name, 'type' => 'allowance', 'amount' => (float) $adj->amount, 'is_employer' => false];
            }

            $allowances = round(collect($lines)->sum('amount'), 2);
            $gross = round($basic + $allowances, 2);

            // 2. Unpaid leave comes off what was actually earned, before any statutory base is taken.
            $unpaid = $this->unpaidLeave($profile, $run->period, $basic);
            $earned = max(0, round($gross - $unpaid['amount'], 2));

            if ($unpaid['amount'] > 0) {
                $lines[] = [
                    'name' => __('Unpaid leave (:n days)', ['n' => rtrim(rtrim(number_format($unpaid['days'], 1), '0'), '.')]),
                    'type' => 'deduction', 'amount' => $unpaid['amount'], 'is_employer' => false,
                ];
            }

            // 3. Deductions in tax order: pre-tax ones (pension) first so they can shrink the taxable income.
            $deductionComponents = $mine->where('type', 'deduction')->where('employer_paid', false);
            $preTaxTotal = 0.0;

            foreach ($deductionComponents->where('pre_tax', true) as $c) {
                $amount = $c->amountFor($basic, $override($c), $earned, $earned);
                $preTaxTotal += $amount;

                if ($amount > 0) {
                    $lines[] = ['name' => $c->name, 'type' => 'deduction', 'amount' => $amount, 'is_employer' => false];
                }
            }

            $taxable = max(0, round($earned - $preTaxTotal, 2));

            foreach ($deductionComponents->where('pre_tax', false) as $c) {
                $amount = $c->amountFor($basic, $override($c), $earned, $taxable);

                if ($amount > 0) {
                    $lines[] = ['name' => $c->name, 'type' => 'deduction', 'amount' => $amount, 'is_employer' => false];
                }
            }

            foreach ($extras->where('type', 'deduction') as $adj) {
                $lines[] = ['name' => $adj->name, 'type' => 'deduction', 'amount' => (float) $adj->amount, 'is_employer' => false];
            }

            $deductions = round(collect($lines)->where('type', 'deduction')->sum('amount'), 2);

            // 4. Employer-side contributions: a cost to the school, never taken from the employee.
            $employerCost = 0.0;

            foreach ($mine->where('employer_paid', true) as $c) {
                $amount = $c->amountFor($basic, $override($c), $earned, $taxable);

                if ($amount > 0) {
                    $employerCost += $amount;
                    $lines[] = ['name' => $c->name, 'type' => 'deduction', 'amount' => $amount, 'is_employer' => true];
                }
            }

            $payslip = Payslip::withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'payroll_run_id' => $run->id,
                'staff_profile_id' => $profile->id,
                'basic_salary' => $basic,
                'total_allowances' => $allowances,
                'total_deductions' => $deductions,
                'gross_pay' => $gross,
                // Deductions can never push take-home pay below zero.
                'net_pay' => max(0, round($gross - $deductions, 2)),
                'employer_cost' => round($employerCost, 2),
            ]);

            $payslip->lines()->createMany($lines);
        }

        return $skipped;
    }

    /**
     * Approved unpaid-leave days falling inside the month (Mon–Sat, the
     * school week) and their cost at basic ÷ 26 working days.
     *
     * @return array{days: float, amount: float}
     */
    protected function unpaidLeave(StaffProfile $profile, string $period, float $basic): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $requests = LeaveRequest::withoutGlobalScopes()
            ->where('school_id', $profile->school_id)
            ->where('user_id', $profile->user_id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->whereHas('leaveType', fn ($q) => $q->withoutGlobalScopes()->where('is_paid', false))
            ->get();

        $days = 0;

        foreach ($requests as $request) {
            $from = $request->start_date->copy()->max($monthStart);
            $to = $request->end_date->copy()->min($monthEnd);

            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                if (! $d->isSunday()) {
                    $days++;
                }
            }
        }

        return ['days' => (float) $days, 'amount' => round(min($basic, $basic / self::WORKING_DAYS_PER_MONTH * $days), 2)];
    }

    /**
     * Adds Tanzania's usual statutory components as ordinary, editable
     * rows. Returns how many were created (existing names are left alone).
     *
     * IMPORTANT: these are the rates/bands as commonly published; statutory
     * rates change with each Finance Act. A school must check them against
     * TRA / NSSF / WCF before running real payroll — that is why they are
     * plain components the school can edit, not hardcoded rules.
     */
    public function installTanzaniaStatutory(School $school): int
    {
        $defaults = [
            ['name' => 'NSSF (employee 10%)', 'type' => 'deduction', 'calculation' => 'percent', 'base' => 'gross', 'default_amount' => 10, 'pre_tax' => true],
            ['name' => 'PAYE', 'type' => 'deduction', 'calculation' => 'percent', 'base' => 'taxable', 'default_amount' => 0, 'bands' => [
                ['upto' => 270000, 'rate' => 0], ['upto' => 520000, 'rate' => 8], ['upto' => 760000, 'rate' => 20],
                ['upto' => 1000000, 'rate' => 25], ['upto' => null, 'rate' => 30],
            ]],
            ['name' => 'NSSF (employer 10%)', 'type' => 'deduction', 'calculation' => 'percent', 'base' => 'gross', 'default_amount' => 10, 'employer_paid' => true],
            ['name' => 'WCF (employer 0.5%)', 'type' => 'deduction', 'calculation' => 'percent', 'base' => 'gross', 'default_amount' => 0.5, 'employer_paid' => true],
            ['name' => 'SDL (employer 3.5%)', 'type' => 'deduction', 'calculation' => 'percent', 'base' => 'gross', 'default_amount' => 3.5, 'employer_paid' => true],
        ];

        $created = 0;

        foreach ($defaults as $row) {
            $exists = PayrollComponent::withoutGlobalScopes()->withTrashed()
                ->where('school_id', $school->id)->where('name', $row['name'])->exists();

            if (! $exists) {
                PayrollComponent::withoutGlobalScopes()->create([...$row, 'school_id' => $school->id, 'apply_to_all' => true]);
                $created++;
            }
        }

        return $created;
    }

    public function approve(PayrollRun $run, User $approver): PayrollRun
    {
        $this->assertDraft($run);

        if (! $run->payslips()->exists()) {
            throw ValidationException::withMessages(['run' => [__('This run has no payslips — set staff basic salaries first.')]]);
        }

        $run->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);

        return $run;
    }

    public function deleteDraft(PayrollRun $run): void
    {
        $this->assertDraft($run);

        $run->delete();
    }

    public function disburse(Payslip $payslip, string $method, ?string $reference, User $by): Payslip
    {
        return DB::transaction(function () use ($payslip, $method, $reference, $by) {
            $locked = Payslip::withoutGlobalScopes()->lockForUpdate()->findOrFail($payslip->id);
            $run = PayrollRun::withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->payroll_run_id);

            if ($run->isDraft()) {
                throw ValidationException::withMessages(['payslip' => [__('Approve the payroll run before paying salaries.')]]);
            }

            if ($locked->isPaid()) {
                throw ValidationException::withMessages(['payslip' => [__('This payslip has already been paid.')]]);
            }

            $locked->update([
                'payment_status' => 'paid', 'payment_method' => $method, 'payment_reference' => $reference,
                'paid_at' => now(), 'paid_by' => $by->id,
            ]);

            $this->closeRunIfFullyPaid($run);

            return $locked;
        });
    }

    /** @return int number of payslips paid */
    public function disburseAll(PayrollRun $run, string $method, User $by): int
    {
        if ($run->fresh()->isDraft()) {
            throw ValidationException::withMessages(['payslip' => [__('Approve the payroll run before paying salaries.')]]);
        }

        $pending = Payslip::withoutGlobalScopes()->where('payroll_run_id', $run->id)->where('payment_status', 'pending')->pluck('id');
        $paid = 0;

        foreach ($pending as $id) {
            try {
                $this->disburse(Payslip::withoutGlobalScopes()->findOrFail($id), $method, null, $by);
                $paid++;
            } catch (ValidationException) {
                // Paid concurrently by someone else in the meantime — skip it.
            }
        }

        return $paid;
    }

    protected function closeRunIfFullyPaid(PayrollRun $run): void
    {
        $stillPending = Payslip::withoutGlobalScopes()->where('payroll_run_id', $run->id)->where('payment_status', 'pending')->exists();

        if (! $stillPending) {
            $run->update(['status' => 'paid']);
        }
    }

    protected function assertDraft(PayrollRun $run): void
    {
        if (! $run->isDraft()) {
            throw ValidationException::withMessages(['run' => [__('Only a draft payroll run can be changed.')]]);
        }
    }
}
