<?php

namespace Tests\Feature\Academics;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamMarksTest extends TestCase
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
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $subject->classes()->attach($class->id);

        $students = collect(range(1, 2))->map(fn ($i) => Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$name}-{$i}", 'first_name' => "S{$i}", 'last_name' => 'Test',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15',
        ]));

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        $teacher = User::create([
            'school_id' => $school->id, 'name' => "{$name} Teacher",
            'email' => str($name)->slug().'-teacher@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        return [$school, $year, $class, $subject, $students, $admin, $teacher];
    }

    public function test_admin_creates_exam_adds_subject_and_teacher_records_marks(): void
    {
        [$school, $year, $class, $subject, $students, $admin, $teacher] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);

        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Mid-Term',
            'academic_year_id' => $year->id,
            'school_class_ids' => [$class->id],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/exams/{$examId}/subjects", [
            'subject_id' => $subject->id,
            'max_marks' => 100,
        ])->assertOk()->assertJsonPath('data.subjects.0.max_marks', 100);

        // Teacher can't restructure the exam...
        Sanctum::actingAs($teacher);
        $this->postJson("/api/v1/exams/{$examId}/subjects", [
            'subject_id' => $subject->id, 'max_marks' => 50,
        ])->assertForbidden();

        // ...but can record marks.
        $this->postJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks", [
            'school_class_id' => $class->id,
            'records' => [
                ['student_id' => $students[0]->id, 'marks_obtained' => 85],
                ['student_id' => $students[1]->id, 'marks_obtained' => 42.5],
            ],
        ])->assertOk();

        $sheet = $this->getJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks?school_class_id={$class->id}")->assertOk();
        $marks = collect($sheet->json('data.students'))->pluck('marks_obtained', 'id');
        $this->assertEquals(85, $marks[$students[0]->id]);
        $this->assertEquals(42.5, $marks[$students[1]->id]);
    }

    public function test_marks_exceeding_max_marks_are_rejected(): void
    {
        [$school, $year, $class, $subject, $students, $admin] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Mid-Term', 'academic_year_id' => $year->id, 'school_class_ids' => [$class->id],
        ])->json('data.id');
        $this->postJson("/api/v1/exams/{$examId}/subjects", ['subject_id' => $subject->id, 'max_marks' => 50]);

        $this->postJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks", [
            'school_class_id' => $class->id,
            'records' => [['student_id' => $students[0]->id, 'marks_obtained' => 75]],
        ])->assertUnprocessable();
    }

    public function test_cannot_record_marks_for_a_subject_not_attached_to_the_exam(): void
    {
        [$school, $year, $class, $subject, $students, $admin] = $this->makeSchool('Sunrise');

        $otherSubject = Subject::create(['school_id' => $school->id, 'name' => 'English']);

        Sanctum::actingAs($admin);
        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Mid-Term', 'academic_year_id' => $year->id, 'school_class_ids' => [$class->id],
        ])->json('data.id');
        // Note: only $subject (Mathematics) is attached, not $otherSubject.
        $this->postJson("/api/v1/exams/{$examId}/subjects", ['subject_id' => $subject->id, 'max_marks' => 100]);

        $this->postJson("/api/v1/exams/{$examId}/subjects/{$otherSubject->id}/marks", [
            'school_class_id' => $class->id,
            'records' => [['student_id' => $students[0]->id, 'marks_obtained' => 50]],
        ])->assertNotFound();
    }

    public function test_only_users_with_publish_permission_can_publish_an_exam(): void
    {
        [$school, $year, $class, , , $admin, $teacher] = $this->makeSchool('Sunrise');

        Sanctum::actingAs($admin);
        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Mid-Term', 'academic_year_id' => $year->id, 'school_class_ids' => [$class->id],
        ])->json('data.id');

        Sanctum::actingAs($teacher);
        $this->patchJson("/api/v1/exams/{$examId}/publish")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/exams/{$examId}/publish")->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_school_b_cannot_access_school_as_exam_or_record_marks(): void
    {
        [$schoolA, $yearA, $classA, $subjectA, $studentsA, $adminA] = $this->makeSchool('School A');
        [$schoolB, , , , , $adminB] = $this->makeSchool('School B');

        Sanctum::actingAs($adminA);
        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Mid-Term', 'academic_year_id' => $yearA->id, 'school_class_ids' => [$classA->id],
        ])->json('data.id');
        $this->postJson("/api/v1/exams/{$examId}/subjects", ['subject_id' => $subjectA->id, 'max_marks' => 100]);

        Sanctum::actingAs($adminB);
        $this->getJson("/api/v1/exams/{$examId}")->assertNotFound();
        $this->postJson("/api/v1/exams/{$examId}/subjects/{$subjectA->id}/marks", [
            'school_class_id' => $classA->id,
            'records' => [['student_id' => $studentsA[0]->id, 'marks_obtained' => 10]],
        ])->assertNotFound();
    }

    public function test_an_exam_can_span_several_classes_each_marked_independently(): void
    {
        [$school, $year, $class1, $subject, $students1, $admin] = $this->makeSchool('Sunrise');

        $class2 = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 2']);
        $subject->classes()->attach($class2->id);
        $students2 = collect(range(1, 2))->map(fn ($i) => Student::create([
            'school_id' => $school->id, 'school_class_id' => $class2->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-F2-{$i}", 'first_name' => "T{$i}", 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-15',
        ]));

        Sanctum::actingAs($admin);

        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Shared Mid-Term',
            'academic_year_id' => $year->id,
            'school_class_ids' => [$class1->id, $class2->id],
        ])->assertCreated()->json('data.id');

        $exam = $this->getJson("/api/v1/exams/{$examId}")->assertOk();
        $this->assertEqualsCanonicalizing(
            [$class1->id, $class2->id],
            collect($exam->json('data.classes'))->pluck('id')->all()
        );

        $this->postJson("/api/v1/exams/{$examId}/subjects", ['subject_id' => $subject->id, 'max_marks' => 100]);

        // Marking Form 1's register must not touch Form 2's, and vice versa.
        $this->postJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks", [
            'school_class_id' => $class1->id,
            'records' => [['student_id' => $students1[0]->id, 'marks_obtained' => 90]],
        ])->assertOk();
        $this->postJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks", [
            'school_class_id' => $class2->id,
            'records' => [['student_id' => $students2[0]->id, 'marks_obtained' => 60]],
        ])->assertOk();

        $sheet1 = $this->getJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks?school_class_id={$class1->id}")->assertOk();
        $this->assertCount(2, $sheet1->json('data.students')); // Form 1's own two students only
        $this->assertEquals(90, collect($sheet1->json('data.students'))->firstWhere('id', $students1[0]->id)['marks_obtained']);

        $sheet2 = $this->getJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks?school_class_id={$class2->id}")->assertOk();
        $this->assertCount(2, $sheet2->json('data.students')); // Form 2's own two students only
        $this->assertEquals(60, collect($sheet2->json('data.students'))->firstWhere('id', $students2[0]->id)['marks_obtained']);
    }

    public function test_cannot_record_marks_for_a_class_not_part_of_the_exam(): void
    {
        [$school, $year, $class1, $subject, $students1, $admin] = $this->makeSchool('Sunrise');
        $otherClass = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 3']);

        Sanctum::actingAs($admin);
        $examId = $this->postJson('/api/v1/exams', [
            'name' => 'Mid-Term', 'academic_year_id' => $year->id, 'school_class_ids' => [$class1->id],
        ])->json('data.id');
        $this->postJson("/api/v1/exams/{$examId}/subjects", ['subject_id' => $subject->id, 'max_marks' => 100]);

        $this->postJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks", [
            'school_class_id' => $otherClass->id,
            'records' => [['student_id' => $students1[0]->id, 'marks_obtained' => 50]],
        ])->assertUnprocessable();

        $this->getJson("/api/v1/exams/{$examId}/subjects/{$subject->id}/marks?school_class_id={$otherClass->id}")->assertNotFound();
    }
}
