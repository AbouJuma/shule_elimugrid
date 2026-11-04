<?php

namespace Tests\Feature\Admissions;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolAdmin(string $schoolName): array
    {
        $school = School::create(['name' => $schoolName, 'slug' => str($schoolName)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id,
            'name' => $schoolName.' Admin',
            'email' => str($schoolName)->slug().'-admin@test.local',
            'password' => 'password',
            'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin];
    }

    protected function makeAcademicStructure(School $school): array
    {
        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);

        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1', 'order' => 1]);
        $stream = Stream::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Blue']);

        return [$year, $class, $stream];
    }

    public function test_full_admission_workflow_creates_student_and_new_guardian(): void
    {
        [$school, $admin] = $this->makeSchoolAdmin('Sunrise Academy');
        [$year, $class, $stream] = $this->makeAcademicStructure($school);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'date_of_birth' => '2015-04-10',
                'admission_date' => '2026-01-15',
                'academic_year_id' => $year->id,
                'school_class_id' => $class->id,
                'stream_id' => $stream->id,
            ],
            'guardians' => [
                [
                    'name' => 'Jane Doe',
                    'phone' => '0700000000',
                    'email' => 'jane@example.com',
                    'relationship' => 'mother',
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.admission_number', 'STU-00001');
        $response->assertJsonPath('data.full_name', 'John Doe');
        $response->assertJsonPath('data.guardians.0.name', 'Jane Doe');
        $response->assertJsonPath('data.guardians.0.relationship', 'mother');

        $this->assertDatabaseHas('guardians', ['school_id' => $school->id, 'phone' => '0700000000']);
        $this->assertDatabaseHas('students', ['school_id' => $school->id, 'admission_number' => 'STU-00001']);
    }

    public function test_admission_numbers_increment_sequentially_per_school(): void
    {
        [$school, $admin] = $this->makeSchoolAdmin('Sunrise Academy');
        [$year] = $this->makeAcademicStructure($school);

        Sanctum::actingAs($admin);

        foreach (['Alpha', 'Beta'] as $name) {
            $this->postJson('/api/v1/students', [
                'student' => [
                    'first_name' => $name, 'last_name' => 'Test', 'gender' => 'male',
                    'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                    'academic_year_id' => $year->id,
                ],
                'guardians' => [['name' => 'G '.$name, 'phone' => '0700000001', 'relationship' => 'guardian']],
            ])->assertCreated();
        }

        $numbers = \App\Models\Student::orderBy('id')->pluck('admission_number')->all();
        $this->assertSame(['STU-00001', 'STU-00002'], $numbers);
    }

    public function test_second_school_admission_numbers_start_from_one_independently(): void
    {
        [$schoolA, $adminA] = $this->makeSchoolAdmin('School A');
        [$yearA] = $this->makeAcademicStructure($schoolA);
        [$schoolB, $adminB] = $this->makeSchoolAdmin('School B');
        [$yearB] = $this->makeAcademicStructure($schoolB);

        Sanctum::actingAs($adminA);
        $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'A', 'last_name' => 'Student', 'gender' => 'male',
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                'academic_year_id' => $yearA->id,
            ],
            'guardians' => [['name' => 'G A', 'phone' => '0700000002', 'relationship' => 'guardian']],
        ])->assertCreated()->assertJsonPath('data.admission_number', 'STU-00001');

        Sanctum::actingAs($adminB);
        $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'B', 'last_name' => 'Student', 'gender' => 'female',
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                'academic_year_id' => $yearB->id,
            ],
            'guardians' => [['name' => 'G B', 'phone' => '0700000003', 'relationship' => 'guardian']],
        ])->assertCreated()->assertJsonPath('data.admission_number', 'STU-00001');
    }

    public function test_existing_guardian_can_be_linked_instead_of_creating_a_duplicate(): void
    {
        [$school, $admin] = $this->makeSchoolAdmin('Sunrise Academy');
        [$year] = $this->makeAcademicStructure($school);

        $guardian = Guardian::create([
            'school_id' => $school->id, 'name' => 'Existing Parent', 'phone' => '0711111111',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'Sibling', 'last_name' => 'Two', 'gender' => 'female',
                'date_of_birth' => '2016-01-01', 'admission_date' => '2026-01-15',
                'academic_year_id' => $year->id,
            ],
            'guardians' => [['guardian_id' => $guardian->id, 'relationship' => 'mother', 'is_primary' => true]],
        ])->assertCreated();

        $this->assertSame(1, Guardian::count());
    }

    public function test_stream_must_belong_to_the_selected_class(): void
    {
        [$schoolA, $adminA] = $this->makeSchoolAdmin('School A');
        [$yearA, $classA] = $this->makeAcademicStructure($schoolA);
        [$schoolB] = $this->makeSchoolAdmin('School B');
        [, $classB] = $this->makeAcademicStructure($schoolB);

        // A stream from School A's class, deliberately mismatched against
        // a class from a DIFFERENT school entirely.
        $wrongStream = Stream::create(['school_id' => $schoolA->id, 'school_class_id' => $classA->id, 'name' => 'Green']);

        Sanctum::actingAs($adminA);

        $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'Bad', 'last_name' => 'Match', 'gender' => 'male',
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                'academic_year_id' => $yearA->id,
                'school_class_id' => $classA->id,
                'stream_id' => $wrongStream->id + 999, // nonexistent stream id
            ],
            'guardians' => [['name' => 'G', 'phone' => '0700000004', 'relationship' => 'guardian']],
        ])->assertUnprocessable();
    }

    public function test_school_admin_cannot_view_or_update_another_schools_student(): void
    {
        [$schoolA, $adminA] = $this->makeSchoolAdmin('School A');
        [$yearA] = $this->makeAcademicStructure($schoolA);
        [$schoolB, $adminB] = $this->makeSchoolAdmin('School B');

        Sanctum::actingAs($adminA);
        $studentId = $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'A', 'last_name' => 'Student', 'gender' => 'male',
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                'academic_year_id' => $yearA->id,
            ],
            'guardians' => [['name' => 'G', 'phone' => '0700000005', 'relationship' => 'guardian']],
        ])->json('data.id');

        $rawStudent = \App\Models\Student::allTenants()->where('uuid', $studentId)->first();

        Sanctum::actingAs($adminB);
        $this->getJson("/api/v1/students/{$rawStudent->id}")->assertNotFound();
        $this->putJson("/api/v1/students/{$rawStudent->id}", ['first_name' => 'Hijacked'])->assertNotFound();
    }

    public function test_archiving_status_requires_students_archive_permission(): void
    {
        [$school, $admin] = $this->makeSchoolAdmin('Sunrise Academy');
        [$year] = $this->makeAcademicStructure($school);

        Sanctum::actingAs($admin);
        $studentId = $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'A', 'last_name' => 'Student', 'gender' => 'male',
                'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
                'academic_year_id' => $year->id,
            ],
            'guardians' => [['name' => 'G', 'phone' => '0700000006', 'relationship' => 'guardian']],
        ])->json('data.id');

        // Strip the archive permission from School Administrator, keep update.
        setPermissionsTeamId($school->id);
        \Spatie\Permission\Models\Role::findByName('School Administrator', 'web')
            ->revokePermissionTo('students.archive');
        $admin->forgetCachedPermissions();

        $this->putJson("/api/v1/students/{$studentId}", ['status' => 'withdrawn'])
            ->assertUnprocessable();

        $this->putJson("/api/v1/students/{$studentId}", ['first_name' => 'Renamed'])
            ->assertOk();
    }
}
