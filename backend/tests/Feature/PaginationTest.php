<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * End-to-end proof that the configurable page size (App\Support\
 * Pagination\PageSize, wired into every paginated Web controller) works
 * through a real HTTP request on a representative controller — the
 * per-controller PageSize::resolve() call sites are otherwise untested
 * individually, since that would mean duplicating this same assertion
 * into all ~18 controllers for no real extra coverage.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    public function test_per_page_query_param_changes_how_many_rows_a_page_returns(): void
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        foreach (range(1, 25) as $i) {
            Student::create([
                'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
                'admission_number' => "STU-{$i}", 'first_name' => "S{$i}", 'last_name' => 'Test',
                'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
            ]);
        }

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => 'Admin',
            'email' => 'admin@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        // Default (20): 25 students split across two pages.
        $this->actingAs($admin)->get('/students')
            ->assertSee('Showing 1–20 of 25');

        // per_page=50: all 25 fit on one page.
        $this->actingAs($admin)->get('/students?per_page=50')
            ->assertSee('Showing 1–25 of 25');

        // Invalid per_page falls back to the default rather than
        // erroring or returning everything unbounded.
        $this->actingAs($admin)->get('/students?per_page=999999')
            ->assertSee('Showing 1–20 of 25');
    }
}
