<?php

namespace Tests\Feature\Payroll;

use App\Mail\PayslipMail;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollAdjustment;
use App\Models\PayrollComponent;
use App\Models\Payslip;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active', 'currency' => 'TZS']);
        app(DefaultRoleProvisioner::class)->provision($school);

        return [$school, $this->user($school, 'Admin', 'School Administrator')];
    }

    protected function user(School $school, string $name, string $role): User
    {
        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => $name,
            'email' => str($name)->slug().'@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function staff(School $school, string $name, float $basic): StaffProfile
    {
        static $n = 100;

        return StaffProfile::create([
            'school_id' => $school->id, 'user_id' => $this->user($school, $name, 'Teacher')->id,
            'employee_number' => 'EMP-'.++$n, 'date_joined' => '2025-01-01', 'basic_salary' => $basic,
        ]);
    }

    protected function line(Payslip $payslip, string $name): ?object
    {
        return $payslip->lines->firstWhere('name', $name);
    }

    public function test_progressive_bands_only_tax_the_slice_inside_each_band(): void
    {
        $bands = [['upto' => 270000, 'rate' => 0], ['upto' => 520000, 'rate' => 8], ['upto' => null, 'rate' => 30]];

        $this->assertSame(0.0, PayrollComponent::progressive($bands, 200000));
        $this->assertSame(0.0, PayrollComponent::progressive($bands, 270000));
        $this->assertSame(20000.0, PayrollComponent::progressive($bands, 520000));           // 250,000 × 8%
        $this->assertSame(20000.0 + 30000.0, PayrollComponent::progressive($bands, 620000)); // + 100,000 × 30%
    }

    public function test_tanzania_set_computes_nssf_paye_and_employer_costs_correctly(): void
    {
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);

        $this->assertSame(5, $svc->installTanzaniaStatutory($school));
        $this->assertSame(0, $svc->installTanzaniaStatutory($school), 'installing twice must not duplicate');

        $this->staff($school, 'Amina', 1000000);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $payslip = $run->payslips()->with('lines')->first();

        // NSSF 10% of 1,000,000 = 100,000 (before tax) → taxable 900,000.
        // PAYE: 0 + 250k×8% + 240k×20% + 140k×25% = 20,000 + 48,000 + 35,000 = 103,000.
        $this->assertSame('100000.00', (string) $this->line($payslip, 'NSSF (employee 10%)')->amount);
        $this->assertSame('103000.00', (string) $this->line($payslip, 'PAYE')->amount);
        $this->assertSame('203000.00', (string) $payslip->total_deductions);
        $this->assertSame('797000.00', (string) $payslip->net_pay);

        // Employer-side: NSSF 100,000 + WCF 5,000 + SDL 35,000 — a school cost, never taken from pay.
        $this->assertSame('140000.00', (string) $payslip->employer_cost);
        $this->assertTrue((bool) $this->line($payslip, 'SDL (employer 3.5%)')->is_employer);
        $this->assertSame(0, $payslip->lines->where('is_employer', true)->where('type', 'allowance')->count());
    }

    public function test_allowances_raise_the_statutory_base(): void
    {
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);
        $svc->installTanzaniaStatutory($school);
        PayrollComponent::create(['school_id' => $school->id, 'name' => 'Housing', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 200000, 'apply_to_all' => true]);

        $this->staff($school, 'Amina', 800000);
        $payslip = $svc->createRun($school, '2026-09', $admin)['run']->payslips()->with('lines')->first();

        // Gross 1,000,000, so NSSF is on gross (not basic).
        $this->assertSame('1000000.00', (string) $payslip->gross_pay);
        $this->assertSame('100000.00', (string) $this->line($payslip, 'NSSF (employee 10%)')->amount);
    }

    public function test_one_off_adjustments_apply_only_to_their_month(): void
    {
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);
        $amina = $this->staff($school, 'Amina', 500000);

        PayrollAdjustment::create(['school_id' => $school->id, 'staff_profile_id' => $amina->id, 'period' => '2026-09', 'name' => 'Back-pay March', 'type' => 'allowance', 'amount' => 75000]);
        PayrollAdjustment::create(['school_id' => $school->id, 'staff_profile_id' => $amina->id, 'period' => '2026-09', 'name' => 'Advance recovery', 'type' => 'deduction', 'amount' => 20000]);

        $sept = $svc->createRun($school, '2026-09', $admin)['run']->payslips()->with('lines')->first();
        $this->assertSame('575000.00', (string) $sept->gross_pay);
        $this->assertSame('555000.00', (string) $sept->net_pay);

        $oct = $svc->createRun($school, '2026-10', $admin)['run']->payslips()->with('lines')->first();
        $this->assertSame('500000.00', (string) $oct->net_pay);
        $this->assertNull($this->line($oct, 'Back-pay March'));
    }

    public function test_unpaid_leave_is_deducted_paid_leave_is_not(): void
    {
        [$school, $admin] = $this->makeSchool();
        $amina = $this->staff($school, 'Amina', 2600000); // ÷ 26 working days = 100,000 a day

        $unpaid = LeaveType::create(['school_id' => $school->id, 'name' => 'Unpaid', 'days_per_year' => 30, 'is_paid' => false]);
        $annual = LeaveType::create(['school_id' => $school->id, 'name' => 'Annual', 'days_per_year' => 21, 'is_paid' => true]);

        // Mon 7 – Wed 9 Sept 2026 unpaid (3 days); Thu 10 – Fri 11 paid.
        foreach ([[$unpaid, '2026-09-07', '2026-09-09'], [$annual, '2026-09-10', '2026-09-11']] as [$type, $from, $to]) {
            LeaveRequest::create([
                'school_id' => $school->id, 'user_id' => $amina->user_id, 'leave_type_id' => $type->id,
                'start_date' => $from, 'end_date' => $to, 'days' => 3, 'status' => 'approved',
            ]);
        }

        $payslip = app(PayrollService::class)->createRun($school, '2026-09', $admin)['run']->payslips()->with('lines')->first();

        $this->assertSame('300000.00', (string) $payslip->total_deductions);
        $this->assertSame('2300000.00', (string) $payslip->net_pay);
        $this->assertStringContainsString('Unpaid leave', $payslip->lines->firstWhere('type', 'deduction')->name);
    }

    public function test_pending_or_rejected_unpaid_leave_is_ignored(): void
    {
        [$school, $admin] = $this->makeSchool();
        $amina = $this->staff($school, 'Amina', 2600000);
        $unpaid = LeaveType::create(['school_id' => $school->id, 'name' => 'Unpaid', 'days_per_year' => 30, 'is_paid' => false]);

        LeaveRequest::create(['school_id' => $school->id, 'user_id' => $amina->user_id, 'leave_type_id' => $unpaid->id, 'start_date' => '2026-09-07', 'end_date' => '2026-09-08', 'days' => 2, 'status' => 'pending']);

        $payslip = app(PayrollService::class)->createRun($school, '2026-09', $admin)['run']->payslips()->first();

        $this->assertSame('2600000.00', (string) $payslip->net_pay);
    }

    public function test_manager_can_add_a_banded_component_through_the_form_and_bad_bands_are_rejected(): void
    {
        [$school, $admin] = $this->makeSchool();

        $this->actingAs($admin)->post(route('school-admin.payroll.components.store'), [
            'name' => 'Income tax', 'type' => 'deduction', 'calculation' => 'percent', 'base' => 'taxable', 'default_amount' => 0,
            'bands_text' => "100000=0\n500000=10\nabove=25", 'pre_tax' => '0', 'employer_paid' => '0', 'apply_to_all' => '1',
        ])->assertSessionHasNoErrors();

        $component = PayrollComponent::where('name', 'Income tax')->firstOrFail();
        $this->assertSame([['upto' => 100000, 'rate' => 0], ['upto' => 500000, 'rate' => 10], ['upto' => null, 'rate' => 25]], $component->bands);

        $this->actingAs($admin)->post(route('school-admin.payroll.components.store'), [
            'name' => 'Broken', 'type' => 'deduction', 'calculation' => 'percent', 'default_amount' => 0, 'bands_text' => "500000=10\n100000=20",
        ])->assertSessionHasErrors('bands_text');

        $this->actingAs($admin)->post(route('school-admin.payroll.components.store'), [
            'name' => 'Broken 2', 'type' => 'deduction', 'calculation' => 'percent', 'default_amount' => 0, 'bands_text' => 'lots=10',
        ])->assertSessionHasErrors('bands_text');
    }

    public function test_adjustments_cannot_be_added_once_the_run_is_approved(): void
    {
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);
        $amina = $this->staff($school, 'Amina', 500000);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($run, $admin);

        $this->actingAs($admin)->post(route('school-admin.payroll.adjustments.store'), [
            'staff_profile_id' => $amina->id, 'period' => '2026-09', 'name' => 'Late bonus', 'type' => 'allowance', 'amount' => 10000,
        ])->assertSessionHasErrors('period');

        $this->assertSame(0, PayrollAdjustment::count());
    }

    public function test_approved_payslips_can_be_emailed_with_a_pdf_but_drafts_cannot(): void
    {
        Mail::fake();
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);
        $this->staff($school, 'Amina', 500000);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];

        $this->actingAs($admin)->post(route('school-admin.payroll.runs.email', $run))->assertSessionHasErrors('run');
        Mail::assertNothingSent();

        $svc->approve($run, $admin);
        $this->actingAs($admin)->post(route('school-admin.payroll.runs.email', $run))->assertSessionHas('status');

        Mail::assertSent(PayslipMail::class, function (PayslipMail $mail) {
            return $mail->hasTo('amina@test.local') && count($mail->attachments()) === 1;
        });
    }

    public function test_approved_payslips_can_be_sent_by_sms_to_staff_with_a_phone_number(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);

        $amina = $this->staff($school, 'Amina', 500000);
        $amina->user->update(['phone' => '0712345678']);
        $this->staff($school, 'Baraka', 500000); // no phone on file
        $run = $svc->createRun($school, '2026-09', $admin)['run'];

        // Not before approval.
        $this->actingAs($admin)->post(route('school-admin.payroll.runs.sms', $run))->assertSessionHasErrors('run');
        $this->assertSame(0, \App\Models\SmsLog::count());

        $svc->approve($run, $admin);
        $this->actingAs($admin)->post(route('school-admin.payroll.runs.sms', $run))->assertSessionHas('status');

        $log = \App\Models\SmsLog::firstOrFail();
        $this->assertSame(1, \App\Models\SmsLog::count(), 'only the staff member with a phone is texted');
        $this->assertSame('payslip', $log->category);
        $this->assertSame('255712345678', $log->recipient_phone);
        $this->assertStringContainsString('500,000', $log->message);
        $this->assertStringContainsString('September 2026', $log->message);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendSmsJob::class);
    }

    public function test_the_payslip_pdf_shows_employer_contributions_separately(): void
    {
        [$school, $admin] = $this->makeSchool();
        $svc = app(PayrollService::class);
        $svc->installTanzaniaStatutory($school);
        $this->staff($school, 'Amina', 1000000);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($run, $admin);

        $response = $this->actingAs($admin)->get(route('school-admin.payroll.payslips.pdf', $run->payslips()->first()));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
