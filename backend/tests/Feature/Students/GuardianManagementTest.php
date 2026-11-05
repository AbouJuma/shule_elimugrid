<?php

namespace Tests\Feature\Students;

use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** The school-wide Guardians list (separate from a student's own "Guardians" tab) — see App\Http\Controllers\Web\GuardianController. */
class GuardianManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolAndAdmin(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_admin_can_see_and_search_the_guardians_list(): void
    {
        [$school, $admin] = $this->makeSchoolAndAdmin();
        Guardian::create(['school_id' => $school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678']);
        Guardian::create(['school_id' => $school->id, 'name' => 'Baraka Mushi', 'phone' => '0755000111']);

        $this->actingAs($admin)->get('/guardians')
            ->assertOk()->assertSee('Asha Kileo')->assertSee('Baraka Mushi');

        $this->actingAs($admin)->get('/guardians?search=Asha')
            ->assertOk()->assertSee('Asha Kileo')->assertDontSee('Baraka Mushi');
    }

    public function test_admin_can_update_a_guardians_own_details(): void
    {
        [$school, $admin] = $this->makeSchoolAndAdmin();
        $guardian = Guardian::create(['school_id' => $school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678']);

        $this->actingAs($admin)->put("/guardians/{$guardian->id}", [
            'name' => 'Asha K. Mwita', 'phone' => '0799999999', 'email' => 'asha@example.com', 'occupation' => 'Nurse',
        ])->assertRedirect(route('school-admin.guardians.index'));

        $guardian->refresh();
        $this->assertSame('Asha K. Mwita', $guardian->name);
        $this->assertSame('0799999999', $guardian->phone);
        $this->assertSame('asha@example.com', $guardian->email);
        $this->assertSame('Nurse', $guardian->occupation);
    }

    public function test_deleting_a_guardian_detaches_their_children_and_suspends_their_login(): void
    {
        [$school, $admin] = $this->makeSchoolAndAdmin();
        $guardian = Guardian::create(['school_id' => $school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678']);
        $loginUser = User::create(['school_id' => $school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678', 'password' => 'password', 'status' => 'active']);
        $guardian->update(['user_id' => $loginUser->id]);

        $year = \App\Models\AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $student = Student::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'admission_number' => 'STU-1', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ]);
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $this->actingAs($admin)->delete("/guardians/{$guardian->id}")
            ->assertRedirect(route('school-admin.guardians.index'));

        $this->assertSoftDeleted($guardian);
        $this->assertSame(0, $student->guardians()->count());
        $this->assertSame('suspended', $loginUser->fresh()->status);
    }
}
