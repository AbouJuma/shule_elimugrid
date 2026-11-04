<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\TransportTrip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

/**
 * What the portal's landing page shows. Every block is gated by the
 * permission that guards the module behind it, so a teacher never sees fee
 * totals and an accountant never sees leave requests. All queries run under
 * the tenant scope of the signed-in user's school.
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $can = fn (string $permission) => $user->can($permission);
        $today = Carbon::today();

        $stats = [];
        $charts = [];
        $lists = [];

        if ($can('students.view')) {
            $active = Student::query()->where('status', 'active')->count();
            $newThisMonth = Student::query()->where('status', 'active')->where('admission_date', '>=', $today->copy()->startOfMonth())->count();

            $stats[] = ['key' => 'students', 'label' => __('Active students'), 'value' => number_format($active), 'sub' => __(':n new this month', ['n' => $newThisMonth]), 'icon' => 'bi-people', 'tone' => 'navy', 'href' => route('school-admin.students.index')];

            $byClass = SchoolClass::query()->withCount(['students as active_students_count' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('order')->orderBy('name')->get()->filter(fn ($c) => $c->active_students_count > 0);
            $charts['byClass'] = ['title' => __('Students by class'), 'rows' => $byClass->map(fn ($c) => ['label' => $c->name, 'value' => $c->active_students_count])->values()->all()];

            $lists['recentAdmissions'] = Student::query()->with('schoolClass')->where('status', 'active')->latest('id')->limit(5)->get();
        }

        if ($can('hr.view')) {
            $stats[] = ['key' => 'staff', 'label' => __('Staff'), 'value' => number_format(StaffProfile::query()->whereNull('date_left')->count()), 'sub' => __('on the payroll register'), 'icon' => 'bi-person-badge', 'tone' => 'navy', 'href' => route('school-admin.staff.index')];
        }

        if ($can('attendance.view')) {
            // whereNull('subject_id'): whole-day/class rows only — a
            // student can also have per-subject rows for today now, and
            // those must not multiply this count.
            $todayRows = AttendanceRecord::query()->whereDate('date', $today)->whereNull('subject_id')->get(['status', 'school_class_id']);
            $rate = $todayRows->isNotEmpty() ? round($todayRows->whereIn('status', ['present', 'late'])->count() / $todayRows->count() * 100) : null;
            $classesMarked = $todayRows->pluck('school_class_id')->unique()->count();
            $classesTotal = SchoolClass::query()->whereHas('students', fn ($q) => $q->where('status', 'active'))->count();

            $stats[] = [
                'key' => 'attendance', 'label' => __('Attendance today'), 'value' => $rate === null ? '—' : $rate.'%',
                'sub' => $rate === null ? __('Not marked yet') : __(':m of :t classes marked', ['m' => $classesMarked, 't' => $classesTotal]),
                'icon' => 'bi-check2-square', 'tone' => $rate === null ? 'warning' : ($rate >= 90 ? 'green' : 'gold'), 'href' => route('school-admin.attendance.index'),
            ];

            $week = collect(range(6, 0))->map(function ($ago) use ($today) {
                $day = $today->copy()->subDays($ago);
                $rows = AttendanceRecord::query()->whereDate('date', $day)->whereNull('subject_id')->get(['status']);

                return [
                    'label' => $day->translatedFormat('D'),
                    'value' => $rows->isNotEmpty() ? round($rows->whereIn('status', ['present', 'late'])->count() / $rows->count() * 100) : null,
                ];
            });
            $charts['attendance'] = ['title' => __('Attendance — last 7 days'), 'rows' => $week->all(), 'suffix' => '%', 'max' => 100];
        }

        if ($can('fees.view')) {
            $monthStart = $today->copy()->startOfMonth();
            $collected = (float) Payment::query()->where('paid_on', '>=', $monthStart)->sum('amount');
            $outstanding = (float) Invoice::query()->whereIn('status', ['unpaid', 'partial'])->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due')->value('due');
            $overdue = Invoice::query()->whereIn('status', ['unpaid', 'partial'])->whereNotNull('due_date')->where('due_date', '<', $today)->count();

            $stats[] = ['key' => 'collected', 'label' => __('Fees collected this month'), 'value' => number_format($collected), 'sub' => $this->currency($user), 'icon' => 'bi-cash-coin', 'tone' => 'green', 'href' => route('school-admin.invoices.index')];
            $stats[] = ['key' => 'outstanding', 'label' => __('Outstanding fees'), 'value' => number_format($outstanding), 'sub' => $overdue > 0 ? __(':n overdue invoices', ['n' => $overdue]) : $this->currency($user), 'icon' => 'bi-hourglass-split', 'tone' => $overdue > 0 ? 'warning' : 'gold', 'href' => route('school-admin.invoices.index')];

            $months = collect(range(5, 0))->map(function ($ago) use ($today) {
                $m = $today->copy()->startOfMonth()->subMonths($ago);

                return [
                    'label' => $m->translatedFormat('M'),
                    'value' => (float) Payment::query()->whereBetween('paid_on', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount'),
                ];
            });
            $charts['fees'] = ['title' => __('Fees collected — last 6 months'), 'rows' => $months->all(), 'money' => true];

            $lists['recentPayments'] = Payment::query()->with(['student', 'invoice'])->where('type', 'payment')->latest('id')->limit(5)->get();
        }

        if ($can('transport.manage')) {
            $running = TransportTrip::query()->with(['vehicle.driver', 'route'])->where('status', 'in_progress')->latest('started_at')->get();
            $stats[] = [
                'key' => 'buses', 'label' => __('Buses on the road'), 'value' => (string) $running->count(),
                'sub' => __(':n active vehicles', ['n' => Vehicle::query()->where('status', 'active')->count()]),
                'icon' => 'bi-bus-front', 'tone' => $running->isNotEmpty() ? 'green' : 'navy', 'href' => route('school-admin.transport-live'),
            ];
            $lists['trips'] = $running;
        }

        if ($can('leave.approve')) {
            $pending = LeaveRequest::query()->with(['user', 'leaveType'])->where('status', 'pending')->latest()->limit(5)->get();
            $stats[] = ['key' => 'leave', 'label' => __('Leave awaiting approval'), 'value' => (string) LeaveRequest::query()->where('status', 'pending')->count(), 'sub' => __('needs your decision'), 'icon' => 'bi-calendar2-check', 'tone' => $pending->isNotEmpty() ? 'warning' : 'navy', 'href' => route('school-admin.leave.index')];
            $lists['leave'] = $pending;
        }

        if ($can('payroll.view')) {
            $run = PayrollRun::query()->withSum('payslips', 'net_pay')->orderByDesc('period')->first();

            if ($run) {
                $stats[] = ['key' => 'payroll', 'label' => __('Payroll :period', ['period' => $run->periodLabel()]), 'value' => number_format((float) $run->payslips_sum_net_pay), 'sub' => __(ucfirst($run->status)), 'icon' => 'bi-wallet2', 'tone' => $run->status === 'draft' ? 'warning' : 'navy', 'href' => route('school-admin.payroll.runs.show', $run)];
            }
        }

        if ($can('exams.view')) {
            $lists['exams'] = Exam::query()->with('classes')->whereIn('status', ['draft', 'ongoing'])
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
                ->orderBy('start_date')->limit(5)->get();
        }

        // Anyone who teaches sees their own day, whatever their role.
        $lessons = TimetableEntry::query()->with(['subject', 'schoolClass', 'stream', 'period'])
            ->where('teacher_user_id', $user->id)->where('day_of_week', $today->dayOfWeekIso)->get()
            ->sortBy(fn ($e) => $e->period?->start_time)->values();

        if ($lessons->isNotEmpty()) {
            $lists['myLessons'] = $lessons;
        }

        return [
            'stats' => $stats,
            'charts' => $charts,
            'lists' => $lists,
            'actions' => $this->actions($can),
            'greeting' => $today->translatedFormat('l, j F Y'),
        ];
    }

    /** @return array<int, array{label: string, icon: string, href: string}> */
    protected function actions(callable $can): array
    {
        $all = [
            ['students.create', __('Admit a student'), 'bi-person-plus', route('school-admin.students.index').'#admit'],
            ['attendance.mark', __('Mark attendance'), 'bi-check2-square', route('school-admin.attendance.index')],
            ['fees.collect', __('Record a payment'), 'bi-cash-stack', route('school-admin.invoices.index')],
            ['results.view', __('Term results'), 'bi-award', route('school-admin.results.index')],
            ['timetable.view', __('Timetable'), 'bi-calendar3', route('school-admin.timetable.index')],
            ['payroll.view', __('Payroll'), 'bi-wallet2', route('school-admin.payroll.index')],
        ];

        return collect($all)->filter(fn ($a) => $can($a[0]))->map(fn ($a) => ['label' => $a[1], 'icon' => $a[2], 'href' => $a[3]])->values()->all();
    }

    protected function currency(User $user): string
    {
        return $user->school?->currency ?? 'TZS';
    }
}
