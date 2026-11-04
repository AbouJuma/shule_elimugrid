<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectCrudTest extends TestCase
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

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    public function test_admin_can_update_and_delete_a_subject(): void
    {
        [, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/subjects', ['name' => 'Mathematics', 'code' => 'MATH']);
        $subject = Subject::first();

        $this->actingAs($admin)->put("/subjects/{$subject->id}", ['name' => 'Math', 'code' => 'MTH'])
            ->assertRedirect(route('school-admin.subjects.index'));
        $this->assertSame('Math', $subject->fresh()->name);

        $this->actingAs($admin)->delete("/subjects/{$subject->id}")
            ->assertRedirect(route('school-admin.subjects.index'));
        $this->assertSoftDeleted($subject);
    }

    public function test_deleting_a_subject_with_exam_records_is_blocked(): void
    {
        [$school, $admin] = $this->makeSchool();
        $this->actingAs($admin)->post('/subjects', ['name' => 'Mathematics']);
        $subject = Subject::first();
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $exam = Exam::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id,
            'name' => 'Midterm', 'status' => 'draft',
        ]);
        $exam->classes()->attach($class->id);
        DB::table('exam_subjects')->insert([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete("/subjects/{$subject->id}")->assertSessionHasErrors('subject');
        $this->assertNotSoftDeleted($subject);
    }
}
