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
use App\Services\TemplateGalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The pre-built design gallery: seeded once, exactly one active at a time, and every layout actually renders a PDF. */
class CertificateTemplateGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'STU-1', 'first_name' => 'John', 'last_name' => 'Doe', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active']);

        setPermissionsTeamId($this->school->id);
        $this->admin = User::create(['school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $this->admin->assignRole('School Administrator');
    }

    public function test_visiting_the_page_seeds_one_template_per_catalog_layout_with_classic_active(): void
    {
        $this->actingAs($this->admin)->get('/certificates')->assertOk();

        $layouts = array_keys(config('template_layouts.certificate'));
        $this->assertSame(count($layouts), CertificateTemplate::count());
        $this->assertEqualsCanonicalizing($layouts, CertificateTemplate::pluck('layout')->all());
        $this->assertSame(1, CertificateTemplate::where('is_default', true)->count());
        $this->assertSame('classic', CertificateTemplate::where('is_default', true)->first()->layout);
    }

    public function test_activating_a_template_deactivates_every_other_one(): void
    {
        app(TemplateGalleryService::class)->ensureCertificateDefaults($this->school);
        $gold = CertificateTemplate::where('layout', 'gold_ribbon')->firstOrFail();

        $this->actingAs($this->admin)->post("/certificates/templates/{$gold->id}/activate")->assertRedirect();

        $this->assertTrue($gold->fresh()->is_default);
        $this->assertSame(1, CertificateTemplate::where('is_default', true)->count());
        $this->assertFalse(CertificateTemplate::where('layout', 'classic')->first()->is_default);
    }

    public function test_issuing_without_choosing_a_template_uses_whichever_is_active(): void
    {
        app(TemplateGalleryService::class)->ensureCertificateDefaults($this->school);
        $diagonal = CertificateTemplate::where('layout', 'modern_diagonal')->firstOrFail();
        app(TemplateGalleryService::class)->activate($diagonal);

        $certificate = app(CertificateService::class)->issue($this->school, $this->student, ['title' => 'Best in Mathematics', 'issued_date' => now()->toDateString()], $this->admin);

        $this->assertSame($diagonal->id, $certificate->certificate_template_id);
    }

    public function test_every_layout_in_the_catalog_actually_renders_a_pdf(): void
    {
        app(TemplateGalleryService::class)->ensureCertificateDefaults($this->school);
        Sanctum::actingAs($this->admin);

        foreach (array_keys(config('template_layouts.certificate')) as $layout) {
            $template = CertificateTemplate::where('layout', $layout)->firstOrFail();
            $certificate = app(CertificateService::class)->issue($this->school, $this->student, ['title' => 'Best in Mathematics', 'certificate_template_id' => $template->id, 'issued_date' => now()->toDateString()], $this->admin);

            $response = $this->get(route('certificates.pdf', $certificate));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), "layout [$layout] did not render a PDF");
        }
    }

    public function test_an_unknown_layout_value_falls_back_to_classic_instead_of_erroring(): void
    {
        $template = CertificateTemplate::create(['school_id' => $this->school->id, 'name' => 'Broken', 'layout' => 'no_such_layout']);
        $certificate = app(CertificateService::class)->issue($this->school, $this->student, ['title' => 'Test', 'certificate_template_id' => $template->id, 'issued_date' => now()->toDateString()], $this->admin);

        $this->actingAs($this->admin)->get(route('certificates.pdf', $certificate))->assertOk();
    }
}
