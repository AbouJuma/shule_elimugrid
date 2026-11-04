<?php

namespace Tests\Feature\Communications;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchoolWithStudent(string $name, ?string $guardianPhone = '0712345678'): array
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
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        if ($guardianPhone) {
            $guardian = Guardian::create(['school_id' => $school->id, 'name' => 'Jane Doe', 'phone' => $guardianPhone]);
            $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);
        }

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $class, $student, $admin];
    }

    public function test_admin_can_send_an_sms_to_a_students_guardian(): void
    {
        [$school, $class, $student, $admin] = $this->makeSchoolWithStudent('Sunrise');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student',
            'student_id' => $student->id,
            'message' => 'Test message',
        ])->assertCreated();

        $response->assertJsonPath('data.recipient_phone', '255712345678');
        $response->assertJsonPath('data.status', 'simulated');

        $this->assertDatabaseHas('sms_logs', [
            'school_id' => $school->id, 'student_id' => $student->id, 'message' => 'Test message',
        ]);
    }

    public function test_sending_to_a_student_with_no_guardian_phone_fails_validation(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent('Sunrise', guardianPhone: null);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student', 'student_id' => $student->id, 'message' => 'Hi',
        ])->assertUnprocessable();
    }

    public function test_sending_to_a_class_reaches_every_student_with_a_guardian_and_skips_the_rest(): void
    {
        [$school, $class, $studentWithPhone, $admin] = $this->makeSchoolWithStudent('Sunrise');

        $studentWithoutPhone = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id,
            'academic_year_id' => $studentWithPhone->academic_year_id,
            'admission_number' => 'STU-Sunrise-2', 'first_name' => 'No', 'last_name' => 'Guardian',
            'gender' => 'female', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'class', 'school_class_id' => $class->id, 'message' => 'Class-wide notice',
        ])->assertCreated();

        $response->assertJsonPath('meta.sent', 1);
        $response->assertJsonPath('meta.skipped', 1);
        $response->assertJsonPath('data.0.status', 'simulated');
    }

    public function test_the_log_driver_marks_a_message_simulated_not_sent(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent('Sunrise');

        Sanctum::actingAs($admin);
        $logId = $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student', 'student_id' => $student->id, 'message' => 'Hi',
        ])->json('data.id');

        $response = $this->getJson('/api/v1/sms-logs')->assertOk();
        $log = collect($response->json('data'))->firstWhere('id', $logId);

        $this->assertSame('simulated', $log['status']);
    }

    public function test_beem_gateway_marks_the_log_sent_on_success_and_records_the_provider_message_id(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent('Sunrise');

        config(['services.sms.driver' => 'beem']);
        config(['services.sms.beem.api_key' => 'key']);
        config(['services.sms.beem.secret_key' => 'secret']);

        Http::fake([
            'apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 999], 200),
        ]);

        Sanctum::actingAs($admin);
        $logId = $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student', 'student_id' => $student->id, 'message' => 'Hi',
        ])->json('data.id');

        $log = collect($this->getJson('/api/v1/sms-logs')->json('data'))->firstWhere('id', $logId);

        $this->assertSame('sent', $log['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'apisms.beem.africa'));
    }

    public function test_beem_gateway_marks_the_log_failed_when_the_provider_rejects_it(): void
    {
        [, , $student, $admin] = $this->makeSchoolWithStudent('Sunrise');

        config(['services.sms.driver' => 'beem']);
        config(['services.sms.beem.api_key' => 'key']);
        config(['services.sms.beem.secret_key' => 'secret']);

        Http::fake([
            'apisms.beem.africa/*' => Http::response(['successful' => false, 'message' => 'Insufficient credit'], 200),
        ]);

        Sanctum::actingAs($admin);
        $logId = $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student', 'student_id' => $student->id, 'message' => 'Hi',
        ])->json('data.id');

        $log = collect($this->getJson('/api/v1/sms-logs')->json('data'))->firstWhere('id', $logId);

        $this->assertSame('failed', $log['status']);
        $this->assertSame('Insufficient credit', $log['error']);
    }

    public function test_teacher_cannot_send_or_manage_templates(): void
    {
        [$school, , $student] = $this->makeSchoolWithStudent('Sunrise');

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-comms@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student', 'student_id' => $student->id, 'message' => 'Hi',
        ])->assertForbidden();
        $this->postJson('/api/v1/message-templates', [
            'name' => 'Test', 'category' => 'general', 'body' => 'Hi',
        ])->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_sms_logs(): void
    {
        [, , $studentA, $adminA] = $this->makeSchoolWithStudent('School A');
        [, , , $adminB] = $this->makeSchoolWithStudent('School B');

        Sanctum::actingAs($adminA);
        $this->postJson('/api/v1/sms-logs', [
            'recipient_type' => 'student', 'student_id' => $studentA->id, 'message' => 'Hi',
        ])->assertCreated();

        Sanctum::actingAs($adminB);
        $logs = $this->getJson('/api/v1/sms-logs')->assertOk()->json('data');
        $this->assertCount(0, $logs);
    }

    public function test_admin_can_create_and_list_a_message_template(): void
    {
        [, , , $admin] = $this->makeSchoolWithStudent('Sunrise');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/message-templates', [
            'name' => 'Fee Reminder', 'category' => 'fee_reminder', 'body' => 'Dear {guardian_name}, balance is {balance}.',
        ])->assertCreated();

        $this->getJson('/api/v1/message-templates')->assertOk()->assertJsonFragment(['name' => 'Fee Reminder']);
    }

    public function test_template_render_replaces_known_placeholders_and_leaves_unknown_ones(): void
    {
        [, , , $admin] = $this->makeSchoolWithStudent('Sunrise');

        setPermissionsTeamId($admin->school_id);
        $template = \App\Models\MessageTemplate::create([
            'school_id' => $admin->school_id, 'name' => 'T', 'category' => 'general',
            'body' => 'Dear {guardian_name}, {unknown_token} balance is {balance}.',
        ]);

        $rendered = $template->render(['guardian_name' => 'Jane', 'balance' => '1,000']);

        $this->assertSame('Dear Jane, {unknown_token} balance is 1,000.', $rendered);
    }
}
