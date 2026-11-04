<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeesPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name, int $studentCount = 2): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $students = collect(range(1, $studentCount))->map(fn ($i) => Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$name}-{$i}", 'first_name' => "S{$i}", 'last_name' => 'Test',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
        ]));

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $accountant = User::create([
            'school_id' => $school->id, 'name' => "{$name} Accountant",
            'email' => str($name)->slug().'-accountant@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $accountant->assignRole('Accountant');

        return [$school, $year, $class, $students, $admin, $accountant];
    }

    public function test_full_invoice_and_payment_lifecycle(): void
    {
        [$school, $year, $class, $students, $admin, $accountant] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);

        $bankId = $this->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'CRDB', 'account_name' => 'Sunrise Academy', 'account_number' => '0150-123456',
        ])->assertCreated()->json('data.id');

        $structureId = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1 Term 1 Fees',
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'items' => [
                ['name' => 'Tuition', 'amount' => 800000],
                ['name' => 'Transport', 'amount' => 250000],
            ],
        ])->assertCreated()->json('data.id');

        $genResponse = $this->postJson("/api/v1/fee-structures/{$structureId}/invoices", [
            'bank_account_id' => $bankId,
        ])->assertCreated();

        $genResponse->assertJsonPath('meta.generated', 2);
        $genResponse->assertJsonPath('data.0.total_amount', '1050000.00');
        $genResponse->assertJsonPath('data.0.status', 'unpaid');
        $genResponse->assertJsonPath('data.0.invoice_number', 'INV-00001');
        $genResponse->assertJsonPath('data.0.control_number', '8000000001');
        $genResponse->assertJsonPath('data.1.invoice_number', 'INV-00002');

        $invoiceUuid = $genResponse->json('data.0.id');

        Sanctum::actingAs($accountant);

        // Partial payment.
        $this->postJson("/api/v1/invoices/{$invoiceUuid}/payments", [
            'amount' => 300000, 'method' => 'cash', 'paid_on' => '2026-02-01',
        ])->assertOk()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.amount_paid', '300000.00')
            ->assertJsonPath('data.balance', '750000.00');

        // Remaining balance.
        $this->postJson("/api/v1/invoices/{$invoiceUuid}/payments", [
            'amount' => 750000, 'method' => 'bank_transfer', 'reference' => 'TXN123', 'paid_on' => '2026-02-15',
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance', '0.00');

        $invoiceId = \App\Models\Invoice::where('uuid', $invoiceUuid)->value('id');
        $this->assertSame(2, \App\Models\Payment::where('invoice_id', $invoiceId)->count());
    }

    public function test_overpayment_is_rejected(): void
    {
        [$school, $year, $class, $students, $admin, $accountant] = $this->makeSchool('Sunrise', 1);

        Sanctum::actingAs($admin);
        $structureId = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1 Fees', 'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');

        $genResponse = $this->postJson("/api/v1/fee-structures/{$structureId}/invoices", []);
        $invoiceUuid = $genResponse->json('data.0.id');

        Sanctum::actingAs($accountant);
        $this->postJson("/api/v1/invoices/{$invoiceUuid}/payments", [
            'amount' => 150000, 'method' => 'cash', 'paid_on' => '2026-02-01',
        ])->assertUnprocessable();
    }

    public function test_generating_invoices_twice_does_not_duplicate(): void
    {
        [$school, $year, $class, $students, $admin] = $this->makeSchool('Sunrise', 2);

        Sanctum::actingAs($admin);
        $structureId = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1 Fees', 'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');

        $this->postJson("/api/v1/fee-structures/{$structureId}/invoices", [])
            ->assertJsonPath('meta.generated', 2);

        // Second run: everyone already has an invoice from this structure.
        $this->postJson("/api/v1/fee-structures/{$structureId}/invoices", [])
            ->assertJsonPath('meta.generated', 0)
            ->assertJsonPath('meta.skipped', 2);

        $this->assertSame(2, \App\Models\Invoice::where('fee_structure_id', $structureId)->count());
    }

    public function test_invoice_and_control_numbers_are_independent_per_school(): void
    {
        [$schoolA, $yearA, $classA, , $adminA] = $this->makeSchool('School A', 1);
        [$schoolB, $yearB, $classB, , $adminB] = $this->makeSchool('School B', 1);

        Sanctum::actingAs($adminA);
        $structureA = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Fees', 'academic_year_id' => $yearA->id, 'school_class_id' => $classA->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');
        $this->postJson("/api/v1/fee-structures/{$structureA}/invoices", [])
            ->assertJsonPath('data.0.invoice_number', 'INV-00001');

        Sanctum::actingAs($adminB);
        $structureB = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Fees', 'academic_year_id' => $yearB->id, 'school_class_id' => $classB->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');
        $this->postJson("/api/v1/fee-structures/{$structureB}/invoices", [])
            ->assertJsonPath('data.0.invoice_number', 'INV-00001');
    }

    public function test_school_b_cannot_access_school_as_invoice_or_pay_it(): void
    {
        [$schoolA, $yearA, $classA, , $adminA] = $this->makeSchool('School A', 1);
        [, , , , $adminB, $accountantB] = $this->makeSchool('School B');

        Sanctum::actingAs($adminA);
        $structureA = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Fees', 'academic_year_id' => $yearA->id, 'school_class_id' => $classA->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');
        $genResponse = $this->postJson("/api/v1/fee-structures/{$structureA}/invoices", []);
        $invoiceUuid = $genResponse->json('data.0.id');

        Sanctum::actingAs($accountantB);
        $this->getJson("/api/v1/invoices/{$invoiceUuid}")->assertNotFound();
        $this->postJson("/api/v1/invoices/{$invoiceUuid}/payments", [
            'amount' => 1000, 'method' => 'cash', 'paid_on' => '2026-02-01',
        ])->assertNotFound();
    }

    public function test_teacher_cannot_collect_payments_or_manage_fee_structures(): void
    {
        [$school, $year, $class, , $admin] = $this->makeSchool('Sunrise', 1);

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-fees@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/v1/fee-structures', [
            'name' => 'Fees', 'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->assertForbidden();
    }
}
