<?php

namespace Tests\Feature\Certificates;

use App\Models\AcademicYear;
use App\Models\CertificateTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CertificateTemplateDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStudent(string $name = 'Sunrise'): array
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
            'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $student, $admin];
    }

    public function test_admin_can_create_a_template_with_design_fields_and_update_and_delete_it(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/certificates/templates', [
            'name' => 'Vertical Gold', 'type' => 'achievement', 'orientation' => 'portrait',
            'primary_color' => '#112233', 'secondary_color' => '#aabbcc', 'custom_text' => 'Principal',
        ])->assertRedirect(route('school-admin.certificates.index'));

        $template = CertificateTemplate::first();
        $this->assertSame('portrait', $template->orientation);

        $this->actingAs($admin)->put("/certificates/templates/{$template->id}", [
            'name' => 'Vertical Gold', 'type' => 'achievement', 'orientation' => 'landscape',
            'primary_color' => '#000000', 'secondary_color' => '#ffffff',
        ])->assertRedirect(route('school-admin.certificates.index'));
        $this->assertSame('landscape', $template->fresh()->orientation);

        $this->actingAs($admin)->delete("/certificates/templates/{$template->id}")
            ->assertRedirect(route('school-admin.certificates.index'));
        $this->assertSoftDeleted($template);
    }

    public function test_certificate_pdf_renders_in_portrait_with_a_template(): void
    {
        [$school, $student, $admin] = $this->makeSchoolWithStudent();

        $template = CertificateTemplate::create([
            'school_id' => $school->id, 'name' => 'Vertical', 'type' => 'achievement',
            'orientation' => 'portrait', 'primary_color' => '#123456', 'secondary_color' => '#654321',
            'custom_text' => 'Signed by the Principal',
        ]);

        $certificate = app(CertificateService::class)->issue($school, $student, [
            'certificate_template_id' => $template->id, 'title' => 'Certificate of Achievement',
            'issued_date' => now()->toDateString(),
        ], $admin);

        $response = $this->actingAs($admin)->get(route('certificates.pdf', $certificate));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_certificate_pdf_renders_with_no_template(): void
    {
        [$school, $student, $admin] = $this->makeSchoolWithStudent();

        $certificate = app(CertificateService::class)->issue($school, $student, [
            'title' => 'Certificate of Achievement', 'issued_date' => now()->toDateString(),
        ], $admin);

        $response = $this->actingAs($admin)->get(route('certificates.pdf', $certificate));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
