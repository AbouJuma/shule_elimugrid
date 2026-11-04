<?php

namespace Tests\Feature\Certificates;

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

class CertificatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$name}-1", 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $student, $admin];
    }

    public function test_admin_can_issue_a_certificate_with_sequential_numbers(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id,
            'title' => 'Certificate of Completion',
            'achievement' => 'for outstanding performance in Form 1',
            'issued_date' => '2026-06-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.certificate_number', 'CERT-00001');
        $response->assertJsonPath('data.status', 'valid');
        $response->assertJsonPath('data.student.name', 'John Doe');
        $this->assertNotEmpty($response->json('data.verification_url'));

        $second = $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id, 'title' => 'Second Certificate', 'issued_date' => '2026-06-02',
        ])->assertCreated();
        $second->assertJsonPath('data.certificate_number', 'CERT-00002');
    }

    public function test_public_verification_page_shows_valid_certificate(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $certificate = \App\Models\Certificate::find(
            $this->postJson('/api/v1/certificates', [
                'student_id' => $student->id, 'title' => 'Certificate of Completion', 'issued_date' => '2026-06-01',
            ])->json('data.id')
        );

        $response = $this->get("/verify/certificates/{$certificate->verification_token}");
        $response->assertOk();
        $response->assertSee('Certificate Verified');
        $response->assertSee('John Doe');
        $response->assertSee($certificate->certificate_number);
    }

    public function test_public_verification_page_shows_not_found_for_bogus_token(): void
    {
        $response = $this->get('/verify/certificates/does-not-exist-at-all');
        $response->assertOk();
        $response->assertSee('Certificate Not Found');
    }

    public function test_revoked_certificate_shows_as_revoked_on_public_page(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $certificateId = $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id, 'title' => 'Certificate of Completion', 'issued_date' => '2026-06-01',
        ])->json('data.id');

        $this->patchJson("/api/v1/certificates/{$certificateId}/revoke", ['reason' => 'Issued in error'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $certificate = \App\Models\Certificate::find($certificateId);
        $response = $this->get("/verify/certificates/{$certificate->verification_token}");
        $response->assertSee('Certificate Revoked');
    }

    public function test_revoking_an_already_revoked_certificate_is_rejected(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $certificateId = $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id, 'title' => 'Certificate', 'issued_date' => '2026-06-01',
        ])->json('data.id');

        $this->patchJson("/api/v1/certificates/{$certificateId}/revoke")->assertOk();
        $this->patchJson("/api/v1/certificates/{$certificateId}/revoke")->assertUnprocessable();
    }

    public function test_school_b_cannot_issue_or_view_school_as_certificate(): void
    {
        [$schoolA, $studentA, $adminA] = $this->makeSchool('School A');
        [, , $adminB] = $this->makeSchool('School B');

        Sanctum::actingAs($adminA);
        $certificateId = $this->postJson('/api/v1/certificates', [
            'student_id' => $studentA->id, 'title' => 'Certificate', 'issued_date' => '2026-06-01',
        ])->json('data.id');

        Sanctum::actingAs($adminB);
        $this->getJson("/api/v1/certificates/{$certificateId}")->assertNotFound();
        $this->patchJson("/api/v1/certificates/{$certificateId}/revoke")->assertNotFound();

        // Cannot issue a certificate to a student from a different school either.
        $this->postJson('/api/v1/certificates', [
            'student_id' => $studentA->id, 'title' => 'Hijacked', 'issued_date' => '2026-06-01',
        ])->assertUnprocessable();
    }

    public function test_teacher_cannot_issue_certificates(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-cert@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id, 'title' => 'Certificate', 'issued_date' => '2026-06-01',
        ])->assertForbidden();
    }

    public function test_verification_tokens_are_unique_and_unguessable(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $first = $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id, 'title' => 'One', 'issued_date' => '2026-06-01',
        ])->json('data.id');
        $second = $this->postJson('/api/v1/certificates', [
            'student_id' => $student->id, 'title' => 'Two', 'issued_date' => '2026-06-02',
        ])->json('data.id');

        $tokenA = \App\Models\Certificate::find($first)->verification_token;
        $tokenB = \App\Models\Certificate::find($second)->verification_token;

        $this->assertNotSame($tokenA, $tokenB);
        $this->assertGreaterThanOrEqual(40, strlen($tokenA));
    }
}
