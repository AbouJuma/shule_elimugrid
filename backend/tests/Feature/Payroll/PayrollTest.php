<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active', 'currency' => 'TZS']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = $this->user($school, 'Admin', 'School Administrator', $name);

        return [$school, $admin];
    }

    protected function user(School $school, string $name, string $role, string $tag = 'x'): User
    {
        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => $name,
            'email' => str("{$tag}-{$name}")->slug().'@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function staff(School $school, string $name, float $basic, array $extra = []): StaffProfile
    {
        $user = $this->user($school, $name, 'Teacher', $school->slug);
        static $n = 0;

        return StaffProfile::create([
            'school_id' => $school->id, 'user_id' => $user->id, 'employee_number' => 'EMP-'.str_pad((string) ++$n, 5, '0', STR_PAD_LEFT),
            'date_joined' => '2025-01-01', 'basic_salary' => $basic, ...$extra,
        ]);
    }

    public function test_run_builds_payslips_from_components_percentages_and_overrides(): void
    {
        [$school, $admin] = $this->makeSchool();
        $housing = PayrollComponent::create(['school_id' => $school->id, 'name' => 'Housing', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 100000, 'apply_to_all' => true]);
        $pension = PayrollComponent::create(['school_id' => $school->id, 'name' => 'Pension', 'type' => 'deduction', 'calculation' => 'percent', 'default_amount' => 10, 'apply_to_all' => true]);
        $loan = PayrollComponent::create(['school_id' => $school->id, 'name' => 'Loan', 'type' => 'deduction', 'calculation' => 'fixed', 'default_amount' => 0, 'apply_to_all' => false]);

        $amina = $this->staff($school, 'Amina', 1000000);
        $baraka = $this->staff($school, 'Baraka', 2000000);
        $baraka->salaryComponents()->create(['payroll_component_id' => $loan->id, 'amount' => 50000]);
        $baraka->salaryComponents()->create(['payroll_component_id' => $housing->id, 'amount' => 0]); // opted out

        $result = app(PayrollService::class)->createRun($school, '2026-09', $admin);

        $a = Payslip::where('staff_profile_id', $amina->id)->first();
        $this->assertEquals(100000, $a->total_allowances);
        $this->assertEquals(100000, $a->total_deductions); // 10% of 1,000,000
        $this->assertEquals(1100000, $a->gross_pay);
        $this->assertEquals(1000000, $a->net_pay);
        $this->assertSame(['Housing', 'Pension'], $a->lines()->orderBy('id')->pluck('name')->all());

        $b = Payslip::where('staff_profile_id', $baraka->id)->first();
        $this->assertEquals(0, $b->total_allowances);          // housing overridden to 0 -> excluded
        $this->assertEquals(250000, $b->total_deductions);     // 10% of 2,000,000 + loan 50,000
        $this->assertEquals(1750000, $b->net_pay);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame('draft', $result['run']->status);
    }

    public function test_staff_without_a_basic_salary_or_who_have_left_or_are_suspended_are_skipped(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->staff($school, 'Paid', 500000);
        $this->staff($school, 'NoSalary', 0);
        $this->staff($school, 'Left', 500000, ['date_left' => '2026-01-01']);
        $suspended = $this->staff($school, 'Suspended', 500000);
        $suspended->user->update(['status' => 'suspended']);

        $result = app(PayrollService::class)->createRun($school, '2026-09', $admin);

        $this->assertSame(1, $result['run']->payslips()->count());
        $this->assertSame(1, $result['skipped']);
    }

    public function test_deductions_never_push_net_pay_below_zero(): void
    {
        [$school, $admin] = $this->makeSchool();
        PayrollComponent::create(['school_id' => $school->id, 'name' => 'Big loan', 'type' => 'deduction', 'calculation' => 'fixed', 'default_amount' => 900000, 'apply_to_all' => true]);
        $this->staff($school, 'Tiny', 100000);

        app(PayrollService::class)->createRun($school, '2026-09', $admin);

        $this->assertEquals(0, Payslip::first()->net_pay);
    }

    public function test_only_one_run_per_month_and_period_format_is_validated(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->staff($school, 'Amina', 500000);

        $this->actingAs($admin)->post('/payroll/runs', ['period' => '2026-09'])->assertRedirect();
        $this->from('/payroll')->actingAs($admin)->post('/payroll/runs', ['period' => '2026-09'])->assertSessionHasErrors('period');
        $this->from('/payroll')->actingAs($admin)->post('/payroll/runs', ['period' => '2026-13'])->assertSessionHasErrors('period');
        $this->assertSame(1, PayrollRun::count());
    }

    public function test_draft_can_be_regenerated_then_approved_and_becomes_immutable(): void
    {
        [$school, $admin] = $this->makeSchool();
        $profile = $this->staff($school, 'Amina', 500000);
        $svc = app(PayrollService::class);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];

        $profile->update(['basic_salary' => 600000]);
        $svc->regenerate($school, $run);
        $this->assertEquals(600000, $run->payslips()->first()->basic_salary);

        $svc->approve($run, $admin);
        $this->assertSame('approved', $run->fresh()->status);

        $this->expectException(ValidationException::class);
        $svc->regenerate($school, $run->fresh());
    }

    public function test_cannot_disburse_before_approval(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->staff($school, 'Amina', 500000);
        $run = app(PayrollService::class)->createRun($school, '2026-09', $admin)['run'];

        $this->from('/payroll')->actingAs($admin)
            ->post('/payroll/payslips/'.$run->payslips()->first()->uuid.'/disburse', ['payment_method' => 'cash'])
            ->assertSessionHasErrors('payslip');

        $this->assertSame('pending', $run->payslips()->first()->payment_status);
    }

    public function test_disbursing_marks_paid_once_and_closes_the_run_when_everyone_is_paid(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->staff($school, 'Amina', 500000);
        $this->staff($school, 'Baraka', 700000);
        $svc = app(PayrollService::class);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($run, $admin);

        [$first, $second] = $run->payslips()->get()->all();

        $this->actingAs($admin)->post("/payroll/payslips/{$first->uuid}/disburse", ['payment_method' => 'bank_transfer', 'payment_reference' => 'TXN1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $first->fresh()->payment_status);
        $this->assertSame('TXN1', $first->fresh()->payment_reference);
        $this->assertSame('approved', $run->fresh()->status);

        // A second attempt on the same payslip is refused — no double payment.
        $this->from('/payroll')->actingAs($admin)->post("/payroll/payslips/{$first->uuid}/disburse", ['payment_method' => 'cash'])
            ->assertSessionHasErrors('payslip');
        $this->assertSame('bank_transfer', $first->fresh()->payment_method);

        $this->actingAs($admin)->post("/payroll/payslips/{$second->uuid}/disburse", ['payment_method' => 'mobile_money']);
        $this->assertSame('paid', $run->fresh()->status);
    }

    public function test_pay_all_pays_every_pending_payslip(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->staff($school, 'Amina', 500000);
        $this->staff($school, 'Baraka', 700000);
        $svc = app(PayrollService::class);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($run, $admin);

        $this->actingAs($admin)->post("/payroll/runs/{$run->uuid}/disburse-all", ['payment_method' => 'bank_transfer'])->assertSessionHasNoErrors();

        $this->assertSame(0, $run->payslips()->where('payment_status', 'pending')->count());
        $this->assertSame('paid', $run->fresh()->status);
    }

    public function test_only_draft_runs_can_be_deleted(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->staff($school, 'Amina', 500000);
        $svc = app(PayrollService::class);
        $draft = $svc->createRun($school, '2026-08', $admin)['run'];
        $approved = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($approved, $admin);

        $this->actingAs($admin)->delete("/payroll/runs/{$draft->uuid}")->assertRedirect(route('school-admin.payroll.index'));
        $this->assertDatabaseMissing('payroll_runs', ['id' => $draft->id]);

        $this->from('/payroll')->actingAs($admin)->delete("/payroll/runs/{$approved->uuid}")->assertSessionHasErrors('run');
        $this->assertDatabaseHas('payroll_runs', ['id' => $approved->id]);
    }

    public function test_hr_can_manage_but_not_disburse_and_accountant_can_disburse_but_not_manage(): void
    {
        [$school, $admin] = $this->makeSchool();
        $hr = $this->user($school, 'Hilda', 'HR', 'sunrise');
        $accountant = $this->user($school, 'Ali', 'Accountant', 'sunrise');
        $this->staff($school, 'Amina', 500000);
        $svc = app(PayrollService::class);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($run, $admin);
        $payslip = $run->payslips()->first();

        $this->actingAs($hr)->post("/payroll/payslips/{$payslip->uuid}/disburse", ['payment_method' => 'cash'])->assertForbidden();
        $this->actingAs($hr)->post('/payroll/components', ['name' => 'Bonus', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 1])->assertRedirect();

        $this->actingAs($accountant)->post('/payroll/components', ['name' => 'Hack', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 1])->assertForbidden();
        $this->actingAs($accountant)->post("/payroll/payslips/{$payslip->uuid}/disburse", ['payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $payslip->fresh()->payment_status);
    }

    public function test_teachers_cannot_see_payroll_at_all(): void
    {
        [$school] = $this->makeSchool();
        $teacher = $this->user($school, 'Teach', 'Teacher', 'sunrise');

        $this->actingAs($teacher)->get('/payroll')->assertForbidden();
    }

    public function test_components_are_validated_and_can_be_edited_and_deleted(): void
    {
        [$school, $admin] = $this->makeSchool();

        $this->from('/payroll')->actingAs($admin)->post('/payroll/components', ['name' => 'Tax', 'type' => 'deduction', 'calculation' => 'percent', 'default_amount' => 150])
            ->assertSessionHasErrors('default_amount');

        $this->actingAs($admin)->post('/payroll/components', ['name' => 'Tax', 'type' => 'deduction', 'calculation' => 'percent', 'default_amount' => 15, 'apply_to_all' => '1']);
        $component = PayrollComponent::first();
        $this->assertTrue($component->apply_to_all);

        $this->actingAs($admin)->put("/payroll/components/{$component->id}", ['name' => 'PAYE', 'type' => 'deduction', 'calculation' => 'percent', 'default_amount' => 12]);
        $this->assertSame('PAYE', $component->fresh()->name);

        $this->actingAs($admin)->delete("/payroll/components/{$component->id}")->assertRedirect();
        $this->assertSame(0, PayrollComponent::count());
    }

    public function test_deleting_a_component_does_not_change_past_payslips(): void
    {
        [$school, $admin] = $this->makeSchool();
        $c = PayrollComponent::create(['school_id' => $school->id, 'name' => 'Housing', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 1000, 'apply_to_all' => true]);
        $this->staff($school, 'Amina', 500000);
        $run = app(PayrollService::class)->createRun($school, '2026-09', $admin)['run'];

        $this->actingAs($admin)->delete("/payroll/components/{$c->id}");

        $this->assertEquals(1000, $run->payslips()->first()->total_allowances);
        $this->assertSame('Housing', $run->payslips()->first()->lines()->first()->name);
    }

    public function test_salary_setup_saves_bank_details_and_component_overrides(): void
    {
        [$school, $admin] = $this->makeSchool();
        $c = PayrollComponent::create(['school_id' => $school->id, 'name' => 'Transport', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 0]);
        $profile = $this->staff($school, 'Amina', 0);

        $this->actingAs($admin)->put("/payroll/staff/{$profile->uuid}/salary", [
            'basic_salary' => 800000, 'bank_name' => 'CRDB', 'bank_account_number' => '0150123',
            'components' => [$c->id => '25000', 999999 => '5'],
        ])->assertRedirect();

        $profile->refresh();
        $this->assertEquals(800000, $profile->basic_salary);
        $this->assertSame('CRDB', $profile->bank_name);
        $this->assertSame(1, $profile->salaryComponents()->count()); // unknown component id ignored
        $this->assertEquals(25000, $profile->salaryComponents()->first()->amount);
    }

    public function test_payslip_pdf_csv_and_pages_render(): void
    {
        [$school, $admin] = $this->makeSchool();
        PayrollComponent::create(['school_id' => $school->id, 'name' => 'Housing', 'type' => 'allowance', 'calculation' => 'fixed', 'default_amount' => 1000, 'apply_to_all' => true]);
        $this->staff($school, 'Amina', 500000, ['bank_name' => 'CRDB', 'bank_account_number' => '015']);
        $svc = app(PayrollService::class);
        $run = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($run, $admin);
        $payslip = $run->payslips()->first();

        $pdf = $this->actingAs($admin)->get("/payroll/payslips/{$payslip->uuid}/pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        preg_match_all('#/Count (\d+)#', $pdf->getContent(), $m);
        $this->assertSame(1, (int) max($m[1]));

        $csv = $this->actingAs($admin)->get("/payroll/runs/{$run->uuid}/export");
        $csv->assertOk();
        $this->assertStringContainsString('Employee No.', $csv->streamedContent());
        $this->assertStringContainsString('CRDB', $csv->streamedContent());

        $this->actingAs($admin)->get('/payroll')->assertOk()->assertSee('September 2026');
        $this->actingAs($admin)->get('/payroll?tab=salaries')->assertOk()->assertSee('Amina');
        $this->actingAs($admin)->get('/payroll?tab=components')->assertOk()->assertSee('Housing');
        $this->actingAs($admin)->get("/payroll/runs/{$run->uuid}")->assertOk()->assertSee('Amina');
    }

    public function test_a_school_cannot_see_or_pay_another_schools_payroll(): void
    {
        [$schoolA, $adminA] = $this->makeSchool('School A');
        [, $adminB] = $this->makeSchool('School B');
        $this->staff($schoolA, 'Amina', 500000);
        $svc = app(PayrollService::class);
        $run = $svc->createRun($schoolA, '2026-09', $adminA)['run'];
        $svc->approve($run, $adminA);
        $payslip = $run->payslips()->first();

        $this->actingAs($adminB)->get("/payroll/runs/{$run->uuid}")->assertNotFound();
        $this->actingAs($adminB)->post("/payroll/payslips/{$payslip->uuid}/disburse", ['payment_method' => 'cash'])->assertNotFound();
        $this->actingAs($adminB)->get("/payroll/payslips/{$payslip->uuid}/pdf")->assertNotFound();
        $this->assertSame('pending', $payslip->fresh()->payment_status);
    }
}
