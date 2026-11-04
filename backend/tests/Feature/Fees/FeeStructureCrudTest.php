<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FeeStructureCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $year, $class, $admin];
    }

    protected function createStructure($admin, $year, $class): FeeStructure
    {
        $this->actingAs($admin)->post('/fee-structures', [
            'name' => 'Term 1 Fees', 'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'items' => [['name' => 'Tuition', 'amount' => 500]],
        ]);

        return FeeStructure::first();
    }

    public function test_admin_can_update_and_delete_a_fee_structure(): void
    {
        [, $year, $class, $admin] = $this->makeSchool();
        $structure = $this->createStructure($admin, $year, $class);

        $this->actingAs($admin)->put("/fee-structures/{$structure->id}", [
            'name' => 'Term 1 Fees (Revised)',
            'items' => [['name' => 'Tuition', 'amount' => 550], ['name' => 'Activity Fee', 'amount' => 50]],
        ])->assertRedirect(route('school-admin.fee-structures.index'));

        $this->assertSame('Term 1 Fees (Revised)', $structure->fresh()->name);
        $this->assertSame(2, $structure->fresh()->items()->count());
        $this->assertSame('600', $structure->fresh()->total());

        $this->actingAs($admin)->delete("/fee-structures/{$structure->id}")
            ->assertRedirect(route('school-admin.fee-structures.index'));
        $this->assertSoftDeleted($structure);
    }

    public function test_deleting_a_fee_structure_with_invoices_is_blocked(): void
    {
        [$school, $year, $class, $admin] = $this->makeSchool();
        $structure = $this->createStructure($admin, $year, $class);

        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);
        Invoice::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'academic_year_id' => $year->id, 'invoice_number' => 'INV-1', 'control_number' => 'CTRL-1',
            'total_amount' => 500, 'amount_paid' => 0, 'status' => 'unpaid',
            'issued_date' => now(), 'due_date' => now()->addDays(30),
        ]);

        $this->actingAs($admin)->delete("/fee-structures/{$structure->id}")->assertSessionHasErrors('fee_structure');
        $this->assertNotSoftDeleted($structure);
    }
}
