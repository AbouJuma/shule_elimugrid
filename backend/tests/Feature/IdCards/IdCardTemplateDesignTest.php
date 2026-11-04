<?php

namespace Tests\Feature\IdCards;

use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\IdCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class IdCardTemplateDesignTest extends TestCase
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

        $this->actingAs($admin)->post('/id-cards/templates', [
            'name' => 'Vertical Navy', 'orientation' => 'portrait', 'photo_position' => 'center',
            'primary_color' => '#112233', 'secondary_color' => '#aabbcc', 'custom_text' => 'Excellence in education',
        ])->assertRedirect(route('school-admin.id-cards.index'));

        $template = IdCardTemplate::first();
        $this->assertSame('portrait', $template->orientation);
        $this->assertSame('center', $template->photo_position);
        $this->assertSame('#112233', $template->primary_color);

        $this->actingAs($admin)->put("/id-cards/templates/{$template->id}", [
            'name' => 'Vertical Navy', 'orientation' => 'landscape', 'photo_position' => 'right',
            'primary_color' => '#000000', 'secondary_color' => '#ffffff',
        ])->assertRedirect(route('school-admin.id-cards.index'));
        $this->assertSame('landscape', $template->fresh()->orientation);
        $this->assertSame('right', $template->fresh()->photo_position);

        $this->actingAs($admin)->delete("/id-cards/templates/{$template->id}")
            ->assertRedirect(route('school-admin.id-cards.index'));
        $this->assertSoftDeleted($template);
    }

    public function test_invalid_color_is_rejected(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();

        $response = $this->from('/id-cards')->actingAs($admin)->post('/id-cards/templates', [
            'name' => 'Bad Color', 'primary_color' => 'navy-blue',
        ]);

        $response->assertSessionHasErrors('primary_color');
        $this->assertSame(0, IdCardTemplate::count());
    }

    public function test_id_card_pdf_renders_for_a_portrait_center_photo_template(): void
    {
        [$school, $student, $admin] = $this->makeSchoolWithStudent();

        $template = IdCardTemplate::create([
            'school_id' => $school->id, 'name' => 'Vertical', 'orientation' => 'portrait',
            'photo_position' => 'center', 'primary_color' => '#123456', 'secondary_color' => '#654321',
            'custom_text' => 'Believe & Achieve',
        ]);

        $idCard = app(IdCardService::class)->issue($school, $student, [
            'id_card_template_id' => $template->id, 'issued_date' => now()->toDateString(),
        ], $admin);

        $response = $this->actingAs($admin)->get(route('id-cards.pdf', $idCard));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_id_card_pdf_renders_with_no_template(): void
    {
        [$school, $student, $admin] = $this->makeSchoolWithStudent();

        $idCard = app(IdCardService::class)->issue($school, $student, [
            'issued_date' => now()->toDateString(),
        ], $admin);

        $response = $this->actingAs($admin)->get(route('id-cards.pdf', $idCard));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
