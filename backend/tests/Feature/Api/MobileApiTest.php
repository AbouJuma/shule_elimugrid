<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\LeaveType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name = 'Sunrise'): School
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        return $school;
    }

    protected function user(School $school, string $name, string $role): User
    {
        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => $name,
            'email' => str("{$school->slug}-{$name}")->slug().'@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function profile(School $school, User $user, float $basic = 500000): StaffProfile
    {
        static $n = 0;

        return StaffProfile::create([
            'school_id' => $school->id, 'user_id' => $user->id, 'employee_number' => 'EMP-'.++$n,
            'date_joined' => '2025-01-01', 'basic_salary' => $basic,
        ]);
    }

    public function test_locale_can_be_saved_and_is_returned_with_the_user(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->user($school, 'Teacher', 'Teacher');
        Sanctum::actingAs($teacher);

        $this->putJson('/api/v1/auth/locale', ['locale' => 'sw'])->assertOk()->assertJsonPath('data.locale', 'sw');
        $this->assertSame('sw', $teacher->fresh()->locale);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.locale', 'sw');

        $this->putJson('/api/v1/auth/locale', ['locale' => 'fr'])->assertUnprocessable();
    }

    public function test_a_teacher_sees_their_own_timetable_and_can_view_a_class_timetable(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->user($school, 'Mr Juma', 'Teacher');
        $other = $this->user($school, 'Ms Neema', 'Teacher');
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $eng = Subject::create(['school_id' => $school->id, 'name' => 'English']);
        $p1 = TimetablePeriod::create(['school_id' => $school->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1]);
        $p2 = TimetablePeriod::create(['school_id' => $school->id, 'name' => 'Period 2', 'start_time' => '08:40', 'end_time' => '09:20', 'sort_order' => 2]);
        TimetableEntry::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'day_of_week' => 1, 'timetable_period_id' => $p1->id, 'subject_id' => $math->id, 'teacher_user_id' => $teacher->id, 'room' => 'R1']);
        TimetableEntry::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'day_of_week' => 1, 'timetable_period_id' => $p2->id, 'subject_id' => $eng->id, 'teacher_user_id' => $other->id]);

        Sanctum::actingAs($teacher);
        $mine = $this->getJson('/api/v1/timetable/mine')->assertOk();
        $mine->assertJsonCount(2, 'data.periods')->assertJsonCount(1, 'data.entries');
        $mine->assertJsonPath('data.entries.0.subject', 'Mathematics')->assertJsonPath('data.entries.0.room', 'R1');

        $this->getJson("/api/v1/timetable/classes/{$class->id}")->assertOk()->assertJsonCount(2, 'data.entries');
    }

    public function test_a_school_cannot_read_another_schools_class_timetable(): void
    {
        $a = $this->makeSchool('A');
        $b = $this->makeSchool('B');
        $classB = SchoolClass::create(['school_id' => $b->id, 'name' => 'Form 1']);

        Sanctum::actingAs($this->user($a, 'Admin', 'School Administrator'));

        $this->getJson("/api/v1/timetable/classes/{$classB->id}")->assertNotFound();
    }

    protected function seedResults(School $school): array
    {
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $term = Term::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1', 'start_date' => '2026-01-05', 'end_date' => '2026-04-01', 'is_current' => true]);
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $exam = Exam::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Midterm']);
        $exam->classes()->attach($class->id);
        DB::table('exam_subjects')->insert(['exam_id' => $exam->id, 'subject_id' => $math->id, 'max_marks' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $students = [];
        foreach ([90, 60] as $i => $marks) {
            $s = Student::create([
                'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
                'admission_number' => 'STU-'.($i + 1), 'first_name' => 'Student'.($i + 1), 'last_name' => 'Test',
                'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
            ]);
            ExamMark::create(['school_id' => $school->id, 'exam_id' => $exam->id, 'subject_id' => $math->id, 'student_id' => $s->id, 'marks_obtained' => $marks]);
            $students[] = $s;
        }

        return [$class, $term, $students];
    }

    public function test_results_are_ranked_and_the_report_card_pdf_downloads_with_a_bearer_token(): void
    {
        $school = $this->makeSchool();
        $admin = $this->user($school, 'Admin', 'School Administrator');
        [$class, $term, $students] = $this->seedResults($school);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/results/terms')->assertOk()->assertJsonPath('data.0.name', 'Term 1');

        $response = $this->getJson("/api/v1/results?term_id={$term->id}&school_class_id={$class->id}")->assertOk();
        $response->assertJsonPath('data.class_size', 2)
            ->assertJsonPath('data.students.0.name', 'Student1 Test')
            ->assertJsonPath('data.students.0.position', 1)
            ->assertJsonPath('data.students.0.grade', 'A')
            ->assertJsonPath('data.students.1.position', 2);

        $this->assertSame('application/pdf', $this->get($response->json('data.class_pdf_url'))->headers->get('content-type'));

        $pdfUrl = $response->json('data.students.0.pdf_url');
        $pdf = $this->get($pdfUrl);
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_results_are_not_available_to_roles_without_the_permission(): void
    {
        $school = $this->makeSchool();
        Sanctum::actingAs($this->user($school, 'Driver', 'Driver'));

        $this->getJson('/api/v1/results/terms')->assertForbidden();
    }

    public function test_staff_see_only_their_own_approved_payslips_and_can_download_them(): void
    {
        $school = $this->makeSchool();
        $admin = $this->user($school, 'Admin', 'School Administrator');
        $amina = $this->user($school, 'Amina', 'Teacher');
        $baraka = $this->user($school, 'Baraka', 'Teacher');
        $this->profile($school, $amina, 500000);
        $this->profile($school, $baraka, 700000);

        $svc = app(PayrollService::class);
        $draft = $svc->createRun($school, '2026-08', $admin)['run'];
        $approved = $svc->createRun($school, '2026-09', $admin)['run'];
        $svc->approve($approved, $admin);

        Sanctum::actingAs($amina);
        $list = $this->getJson('/api/v1/my/payslips')->assertOk();
        $list->assertJsonCount(1, 'data')->assertJsonPath('data.0.period', '2026-09')->assertJsonPath('data.0.net_pay', '500000.00')->assertJsonPath('data.0.currency', $school->currency ?? 'TZS');

        $mine = $approved->payslips()->whereHas('staffProfile', fn ($q) => $q->where('user_id', $amina->id))->first();
        $theirs = $approved->payslips()->whereHas('staffProfile', fn ($q) => $q->where('user_id', $baraka->id))->first();
        $draftMine = $draft->payslips()->whereHas('staffProfile', fn ($q) => $q->where('user_id', $amina->id))->first();

        $this->getJson("/api/v1/my/payslips/{$mine->uuid}")->assertOk();
        $this->getJson("/api/v1/my/payslips/{$theirs->uuid}")->assertNotFound();

        $pdf = $this->get(route('school-admin.payroll.payslips.pdf', $mine));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $this->get(route('school-admin.payroll.payslips.pdf', $theirs))->assertForbidden();
        $this->get(route('school-admin.payroll.payslips.pdf', $draftMine))->assertForbidden(); // not approved yet
    }

    public function test_payroll_staff_can_still_open_any_payslip_pdf(): void
    {
        $school = $this->makeSchool();
        $admin = $this->user($school, 'Admin', 'School Administrator');
        $amina = $this->user($school, 'Amina', 'Teacher');
        $this->profile($school, $amina);
        $run = app(PayrollService::class)->createRun($school, '2026-09', $admin)['run'];

        $this->actingAs($admin)->get(route('school-admin.payroll.payslips.pdf', $run->payslips()->first()))->assertOk();
    }

    public function test_someone_with_no_staff_profile_sees_no_payslips(): void
    {
        $school = $this->makeSchool();
        Sanctum::actingAs($this->user($school, 'Driver', 'Driver'));

        $this->getJson('/api/v1/my/payslips')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_can_apply_for_leave_and_see_their_requests(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->user($school, 'Teacher', 'Teacher');
        $type = LeaveType::create(['school_id' => $school->id, 'name' => 'Annual Leave', 'days_per_year' => 21]);
        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/leave', ['leave_type_id' => $type->uuid, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'reason' => 'Family'])
            ->assertCreated()->assertJsonPath('data.days', 3)->assertJsonPath('data.status', 'pending');

        $list = $this->getJson('/api/v1/leave')->assertOk();
        $list->assertJsonCount(1, 'data.types')->assertJsonCount(1, 'data.my_requests')->assertJsonPath('data.can_approve', false);
        $list->assertJsonCount(0, 'data.pending');

        $this->postJson('/api/v1/leave', ['leave_type_id' => $type->uuid, 'start_date' => '2026-10-10', 'end_date' => '2026-12-31'])
            ->assertUnprocessable(); // exceeds the annual balance
    }

    public function test_approvers_see_the_pending_queue_and_can_approve_or_reject(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->user($school, 'Teacher', 'Teacher');
        $hr = $this->user($school, 'Hilda', 'HR');
        $type = LeaveType::create(['school_id' => $school->id, 'name' => 'Annual Leave', 'days_per_year' => 21]);

        Sanctum::actingAs($teacher);
        $first = $this->postJson('/api/v1/leave', ['leave_type_id' => $type->uuid, 'start_date' => '2026-10-05', 'end_date' => '2026-10-05'])->json('data.id');
        $second = $this->postJson('/api/v1/leave', ['leave_type_id' => $type->uuid, 'start_date' => '2026-10-06', 'end_date' => '2026-10-06'])->json('data.id');

        // A teacher can't action requests.
        $this->patchJson("/api/v1/leave/{$first}/approve")->assertForbidden();

        Sanctum::actingAs($hr);
        $this->getJson('/api/v1/leave')->assertOk()->assertJsonPath('data.can_approve', true)->assertJsonCount(2, 'data.pending');
        $this->patchJson("/api/v1/leave/{$first}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->patchJson("/api/v1/leave/{$second}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->patchJson("/api/v1/leave/{$first}/reject")->assertUnprocessable(); // already actioned
    }

    public function test_a_school_cannot_action_another_schools_leave(): void
    {
        $a = $this->makeSchool('A');
        $b = $this->makeSchool('B');
        $teacher = $this->user($a, 'Teacher', 'Teacher');
        $type = LeaveType::create(['school_id' => $a->id, 'name' => 'Annual Leave', 'days_per_year' => 21]);
        Sanctum::actingAs($teacher);
        $id = $this->postJson('/api/v1/leave', ['leave_type_id' => $type->uuid, 'start_date' => '2026-10-05', 'end_date' => '2026-10-05'])->json('data.id');

        Sanctum::actingAs($this->user($b, 'Hilda', 'HR'));
        $this->patchJson("/api/v1/leave/{$id}/approve")->assertNotFound();
    }

    public function test_the_api_locale_is_used_for_downloaded_report_cards(): void
    {
        $school = $this->makeSchool();
        $admin = $this->user($school, 'Admin', 'School Administrator');
        $admin->forceFill(['locale' => 'sw'])->save();
        [, $term, $students] = $this->seedResults($school);
        Sanctum::actingAs($admin);

        $pdf = $this->get(route('school-admin.results.student-pdf', [$students[0], 'term_id' => $term->id]));

        $pdf->assertOk();
        $this->assertSame('sw', app()->getLocale());
    }

    public function test_api_error_messages_follow_the_users_language(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->user($school, "Teacher", "Teacher");
        $teacher->forceFill(["locale" => "sw"])->save();
        $type = LeaveType::create(["school_id" => $school->id, "name" => "Annual Leave", "days_per_year" => 21]);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/v1/leave", ["leave_type_id" => $type->uuid, "start_date" => "2026-10-05", "end_date" => "2026-10-01"])
            ->assertUnprocessable()
            ->assertJsonFragment(["end_date" => ["Sehemu ya tarehe ya kuisha lazima iwe tarehe sawa au baada ya tarehe ya kuanza."]]);
    }

    public function test_a_student_status_change_over_the_api_needs_a_reason_and_is_logged(): void
    {
        $school = $this->makeSchool();
        $admin = $this->user($school, "Admin", "School Administrator");
        [$class, , $students] = $this->seedResults($school);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/students/{$students[0]->uuid}", ["status" => "withdrawn"])->assertUnprocessable()->assertJsonValidationErrors("reason");
        $this->assertSame("active", $students[0]->fresh()->status);

        $this->putJson("/api/v1/students/{$students[0]->uuid}", ["status" => "withdrawn", "reason" => "Family moved"])->assertOk();
        $this->assertSame("withdrawn", $students[0]->fresh()->status);
        $this->assertDatabaseHas("student_status_changes", ["student_id" => $students[0]->id, "to_status" => "withdrawn", "reason" => "Family moved"]);

        // Non-status edits need no reason.
        $this->putJson("/api/v1/students/{$students[1]->uuid}", ["first_name" => "Renamed"])->assertOk();

        $this->getJson("/api/v1/students?school_class_id={$class->id}")->assertOk();
    }
}
