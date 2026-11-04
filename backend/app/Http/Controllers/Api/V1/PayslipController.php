<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\StaffProfile;
use Illuminate\Http\Request;

/**
 * Every staff member's own payslips. No payroll permission is needed to see
 * your own pay — but draft (not yet approved) runs are never shown, since
 * their figures can still change.
 */
class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $profile = StaffProfile::query()->where('user_id', $request->user()->id)->first();

        if (! $profile) {
            return response()->json(['data' => []]);
        }

        $payslips = Payslip::query()
            ->with('run')
            ->where('staff_profile_id', $profile->id)
            ->whereHas('run', fn ($q) => $q->where('status', '!=', 'draft'))
            ->get()
            ->sortByDesc(fn ($p) => $p->run->period)
            ->values();

        return response()->json(['data' => $payslips->map(fn ($p) => $this->resource($p))]);
    }

    public function show(Request $request, Payslip $payslip)
    {
        abort_unless($this->owns($request, $payslip), 404);

        $payslip->load(['run', 'lines']);

        return response()->json(['data' => $this->resource($payslip, withLines: true)]);
    }

    public static function ownedBy($user, Payslip $payslip): bool
    {
        return $payslip->staffProfile?->user_id === $user->id && $payslip->run->status !== 'draft';
    }

    protected function owns(Request $request, Payslip $payslip): bool
    {
        $payslip->loadMissing(['run', 'staffProfile']);

        return self::ownedBy($request->user(), $payslip);
    }

    protected function resource(Payslip $p, bool $withLines = false): array
    {
        return [
            'id' => $p->uuid,
            'period' => $p->run->period,
            'period_label' => $p->run->periodLabel(),
            'currency' => $p->school?->currency ?? 'TZS',
            'basic_salary' => (string) $p->basic_salary,
            'total_allowances' => (string) $p->total_allowances,
            'total_deductions' => (string) $p->total_deductions,
            'gross_pay' => (string) $p->gross_pay,
            'net_pay' => (string) $p->net_pay,
            'payment_status' => $p->payment_status,
            'payment_method' => $p->payment_method,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'pdf_url' => route('school-admin.payroll.payslips.pdf', $p),
            'lines' => $withLines ? $p->lines->map(fn ($l) => ['name' => $l->name, 'type' => $l->type, 'amount' => (string) $l->amount, 'is_employer' => (bool) $l->is_employer])->values() : null,
        ];
    }
}
