<?php

namespace Tests\Feature\IdCards;

use App\Models\AcademicYear;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\IdCardService;
use App\Services\TemplateGalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The pre-built design gallery: seeded once, exactly one active at a time, and every layout actually renders a PDF. */
class IdCardTemplateGalleryTest extends TestCase
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
        $this->actingAs($this->admin)->get('/id-cards')->assertOk();

        $layouts = array_keys(config('template_layouts.id_card'));
        $this->assertSame(count($layouts), IdCardTemplate::count());
        $this->assertEqualsCanonicalizing($layouts, IdCardTemplate::pluck('layout')->all());
        $this->assertSame(1, IdCardTemplate::where('is_default', true)->count());
        $this->assertSame('classic', IdCardTemplate::where('is_default', true)->first()->layout);

        // Visiting again doesn't duplicate or reset a school's own edits.
        IdCardTemplate::first()->update(['name' => 'Renamed']);
        $this->actingAs($this->admin)->get('/id-cards')->assertOk();
        $this->assertSame(count($layouts), IdCardTemplate::count());
        $this->assertSame(1, IdCardTemplate::where('name', 'Renamed')->count());
    }

    public function test_activating_a_template_deactivates_every_other_one(): void
    {
        app(TemplateGalleryService::class)->ensureIdCardDefaults($this->school);
        $wave = IdCardTemplate::where('layout', 'wave_header')->firstOrFail();

        $this->actingAs($this->admin)->post("/id-cards/templates/{$wave->id}/activate")->assertRedirect();

        $this->assertTrue($wave->fresh()->is_default);
        $this->assertSame(1, IdCardTemplate::where('is_default', true)->count());

        // Saving another template as the new default deactivates it too — "is_default" always means exactly one.
        $mono = IdCardTemplate::where('layout', 'minimal_mono')->firstOrFail();
        $this->actingAs($this->admin)->put("/id-cards/templates/{$mono->id}", [
            'name' => $mono->name, 'layout' => 'minimal_mono', 'orientation' => 'landscape', 'photo_position' => 'left',
            'primary_color' => '#000000', 'secondary_color' => '#ffffff', 'is_default' => true,
        ])->assertRedirect();

        $this->assertTrue($mono->fresh()->is_default);
        $this->assertFalse($wave->fresh()->is_default);
        $this->assertSame(1, IdCardTemplate::where('is_default', true)->count());
    }

    public function test_issuing_without_choosing_a_template_uses_whichever_is_active(): void
    {
        app(TemplateGalleryService::class)->ensureIdCardDefaults($this->school);
        $dark = IdCardTemplate::where('layout', 'dark_executive')->firstOrFail();
        app(TemplateGalleryService::class)->activate($dark);

        $idCard = app(IdCardService::class)->issue($this->school, $this->student, ['issued_date' => now()->toDateString()], $this->admin);

        $this->assertSame($dark->id, $idCard->id_card_template_id);

        // Choosing one explicitly still overrides the active one.
        $classic = IdCardTemplate::where('layout', 'classic')->firstOrFail();
        $chosen = app(IdCardService::class)->issue($this->school, $this->student, ['id_card_template_id' => $classic->id, 'issued_date' => now()->toDateString()], $this->admin);
        $this->assertSame($classic->id, $chosen->id_card_template_id);
    }

    public function test_every_layout_in_the_catalog_actually_renders_a_pdf(): void
    {
        app(TemplateGalleryService::class)->ensureIdCardDefaults($this->school);
        Sanctum::actingAs($this->admin);

        foreach (array_keys(config('template_layouts.id_card')) as $layout) {
            $template = IdCardTemplate::where('layout', $layout)->firstOrFail();
            $idCard = app(IdCardService::class)->issue($this->school, $this->student, ['id_card_template_id' => $template->id, 'issued_date' => now()->toDateString()], $this->admin);

            $response = $this->get(route('id-cards.pdf', $idCard));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'), "layout [$layout] did not render a PDF");
        }
    }

    public function test_an_unknown_layout_value_falls_back_to_classic_instead_of_erroring(): void
    {
        $template = IdCardTemplate::create(['school_id' => $this->school->id, 'name' => 'Broken', 'layout' => 'no_such_layout']);
        $idCard = app(IdCardService::class)->issue($this->school, $this->student, ['id_card_template_id' => $template->id, 'issued_date' => now()->toDateString()], $this->admin);

        $this->actingAs($this->admin)->get(route('id-cards.pdf', $idCard))->assertOk();
    }
}
