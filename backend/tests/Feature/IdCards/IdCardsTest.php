<?php

namespace Tests\Feature\IdCards;

use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdCardsTest extends TestCase
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

    public function test_admin_can_issue_an_id_card_with_sequential_numbers(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/id-cards', [
            'student_id' => $student->id,
            'issued_date' => '2026-06-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.card_number', 'ID-00001');
        $response->assertJsonPath('data.status', 'active');
        $this->assertNotEmpty($response->json('data.verification_url'));
    }

    public function test_issuing_a_replacement_card_supersedes_the_previous_one(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);

        $firstId = $this->postJson('/api/v1/id-cards', [
            'student_id' => $student->id, 'issued_date' => '2026-06-01',
        ])->json('data.id');

        // Card is issued as a raw numeric id internally; find it via the
        // response's uuid-based public id isn't enough here, so query by
        // student directly.
        $firstCard = IdCard::where('student_id', $student->id)->first();
        $this->assertSame('active', $firstCard->status);

        $secondResponse = $this->postJson('/api/v1/id-cards', [
            'student_id' => $student->id, 'issued_date' => '2026-07-01',
        ])->assertCreated();
        $secondResponse->assertJsonPath('data.card_number', 'ID-00002');
        $secondResponse->assertJsonPath('data.status', 'active');

        $this->assertSame('superseded', $firstCard->fresh()->status);
        $this->assertSame(1, IdCard::where('student_id', $student->id)->where('status', 'active')->count());
    }

    public function test_public_verification_page_shows_active_card(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/id-cards', ['student_id' => $student->id, 'issued_date' => '2026-06-01']);
        $card = IdCard::where('student_id', $student->id)->first();

        $response = $this->get("/verify/id-cards/{$card->verification_token}");
        $response->assertOk();
        $response->assertSee('Valid Student ID');
        $response->assertSee('John Doe');
        $response->assertSee($student->admission_number);
    }

    public function test_superseded_card_shows_as_no_longer_valid_on_public_page(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/id-cards', ['student_id' => $student->id, 'issued_date' => '2026-06-01']);
        $firstCard = IdCard::where('student_id', $student->id)->first();

        $this->postJson('/api/v1/id-cards', ['student_id' => $student->id, 'issued_date' => '2026-07-01']);

        $response = $this->get("/verify/id-cards/{$firstCard->verification_token}");
        $response->assertSee('No Longer Valid');
    }

    public function test_revoked_card_shows_as_revoked_on_public_page(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $cardId = $this->postJson('/api/v1/id-cards', [
            'student_id' => $student->id, 'issued_date' => '2026-06-01',
        ])->json('data.id');

        $this->patchJson("/api/v1/id-cards/{$cardId}/revoke", ['reason' => 'Lost'])
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $card = IdCard::find($cardId);
        $response = $this->get("/verify/id-cards/{$card->verification_token}");
        $response->assertSee('ID Card Revoked');
    }

    public function test_cannot_revoke_a_non_active_card(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/id-cards', ['student_id' => $student->id, 'issued_date' => '2026-06-01']);
        $firstCard = IdCard::where('student_id', $student->id)->first();

        // Superseded by a second card.
        $this->postJson('/api/v1/id-cards', ['student_id' => $student->id, 'issued_date' => '2026-07-01']);

        $this->patchJson("/api/v1/id-cards/{$firstCard->id}/revoke")->assertUnprocessable();
    }

    public function test_school_b_cannot_issue_or_view_school_as_id_card(): void
    {
        [$schoolA, $studentA, $adminA] = $this->makeSchool('School A');
        [, , $adminB] = $this->makeSchool('School B');

        Sanctum::actingAs($adminA);
        $cardId = $this->postJson('/api/v1/id-cards', [
            'student_id' => $studentA->id, 'issued_date' => '2026-06-01',
        ])->json('data.id');

        Sanctum::actingAs($adminB);
        $this->getJson("/api/v1/id-cards/{$cardId}")->assertNotFound();
        $this->patchJson("/api/v1/id-cards/{$cardId}/revoke")->assertNotFound();
        $this->postJson('/api/v1/id-cards', [
            'student_id' => $studentA->id, 'issued_date' => '2026-06-01',
        ])->assertUnprocessable();
    }

    public function test_teacher_cannot_issue_id_cards(): void
    {
        [$school, $student, $admin] = $this->makeSchool('Sunrise');

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-idcard@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/v1/id-cards', [
            'student_id' => $student->id, 'issued_date' => '2026-06-01',
        ])->assertForbidden();
    }
}
