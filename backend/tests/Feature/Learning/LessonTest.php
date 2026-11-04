<?php

namespace Tests\Feature\Learning;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    /** A class with a teacher, a subject, and a student whose mother and the student both have logins. */
    protected function scenario(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);

        setPermissionsTeamId($school->id);
        $teacher = User::create(['school_id' => $school->id, 'name' => 'Teacher', 'email' => str($name)->slug().'-teacher@test.local', 'password' => 'password', 'status' => 'active']);
        $teacher->assignRole('Teacher');

        $parentUser = User::create(['school_id' => $school->id, 'name' => 'Mother', 'email' => str($name)->slug().'-mother@test.local', 'password' => 'password', 'status' => 'active']);
        $parentUser->assignRole('Parent');
        $guardian = Guardian::create(['school_id' => $school->id, 'user_id' => $parentUser->id, 'name' => 'Mother', 'phone' => '0700000000']);

        $studentUser = User::create(['school_id' => $school->id, 'name' => 'Student', 'email' => str($name)->slug().'-student@test.local', 'password' => 'password', 'status' => 'active']);
        $studentUser->assignRole('Student');
        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'user_id' => $studentUser->id,
            'admission_number' => 'STU-1', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ])->refresh();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        return compact('school', 'class', 'subject', 'teacher', 'parentUser', 'studentUser', 'student');
    }

    public function test_a_teacher_posts_a_linked_lesson_and_it_reaches_the_parent_and_the_student(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $lessonId = $this->postJson('/api/v1/lessons', [
            'school_class_id' => $d['class']->id,
            'subject_id' => $d['subject']->id,
            'title' => 'Fractions Explained',
            'video_url' => 'https://youtu.be/example',
        ])->assertCreated()->json('data.id');

        foreach (['parentUser', 'studentUser'] as $key) {
            Sanctum::actingAs($d[$key]);
            $response = $this->getJson("/api/v1/my/children/{$d['student']->uuid}/lessons")->assertOk();
            $response->assertJsonFragment(['id' => $lessonId, 'title' => 'Fractions Explained', 'video_url' => 'https://youtu.be/example']);
        }
    }

    public function test_an_uploaded_video_is_streamed_to_the_parent_but_not_to_a_stranger(): void
    {
        $d = $this->scenario();
        $other = $this->scenario('Other');

        Sanctum::actingAs($d['teacher']);
        $video = UploadedFile::fake()->create('lesson.mp4', 500, 'video/mp4');
        $this->postJson('/api/v1/lessons', [
            'school_class_id' => $d['class']->id, 'title' => 'Recorded Lesson', 'video' => $video,
        ])->assertCreated();

        $lesson = \App\Models\Lesson::first();

        Sanctum::actingAs($d['parentUser']);
        $this->get(route('school-admin.lessons.video', $lesson))->assertOk();

        // A parent from a completely different school, no relation at all
        // — the tenant scope on Lesson hides the row entirely for a
        // different school's session, so this reads as "not found".
        Sanctum::actingAs($other['parentUser']);
        $this->get(route('school-admin.lessons.video', $lesson))->assertNotFound();
    }

    public function test_within_the_same_school_a_lesson_for_a_different_class_is_not_streamed_to_this_childs_parent(): void
    {
        $d = $this->scenario();
        $otherClass = SchoolClass::create(['school_id' => $d['school']->id, 'name' => 'Form 2']);

        Sanctum::actingAs($d['teacher']);
        $video = UploadedFile::fake()->create('lesson.mp4', 500, 'video/mp4');
        $this->postJson('/api/v1/lessons', [
            'school_class_id' => $otherClass->id, 'title' => 'Form 2 Only', 'video' => $video,
        ])->assertCreated();

        $lesson = \App\Models\Lesson::where('title', 'Form 2 Only')->firstOrFail();

        Sanctum::actingAs($d['parentUser']);
        $this->get(route('school-admin.lessons.video', $lesson))->assertForbidden();
    }

    public function test_a_teacher_must_provide_either_a_link_or_an_uploaded_file(): void
    {
        $d = $this->scenario();

        Sanctum::actingAs($d['teacher']);
        $this->postJson('/api/v1/lessons', [
            'school_class_id' => $d['class']->id, 'title' => 'No video at all',
        ])->assertUnprocessable();
    }

    public function test_an_unrelated_parent_cannot_see_someone_elses_child(): void
    {
        $d = $this->scenario();
        $other = $this->scenario('Other');

        Sanctum::actingAs($d['teacher']);
        $this->postJson('/api/v1/lessons', [
            'school_class_id' => $d['class']->id, 'title' => 'Private Lesson', 'video_url' => 'https://youtu.be/x',
        ])->assertCreated();

        // Cross-school: the tenant scope on Student hides the row entirely,
        // so this reads as "not found" rather than "forbidden".
        Sanctum::actingAs($other['parentUser']);
        $this->getJson("/api/v1/my/children/{$d['student']->uuid}/lessons")->assertNotFound();
    }

    public function test_within_the_same_school_a_parent_cannot_see_another_parents_child(): void
    {
        $d = $this->scenario();

        setPermissionsTeamId($d['school']->id);
        $otherParentUser = User::create(['school_id' => $d['school']->id, 'name' => 'Someone Else', 'email' => 'someone@test.local', 'password' => 'password', 'status' => 'active']);
        $otherParentUser->assignRole('Parent');
        Guardian::create(['school_id' => $d['school']->id, 'user_id' => $otherParentUser->id, 'name' => 'Someone Else', 'phone' => '0711111111']);

        Sanctum::actingAs($otherParentUser);
        $this->getJson("/api/v1/my/children/{$d['student']->uuid}/lessons")->assertForbidden();
    }

    public function test_a_teacher_can_delete_their_own_lesson_but_an_accountant_cannot_manage_lessons(): void
    {
        $d = $this->scenario();

        setPermissionsTeamId($d['school']->id);
        $accountant = User::create(['school_id' => $d['school']->id, 'name' => 'Accountant', 'email' => 'acc@test.local', 'password' => 'password', 'status' => 'active']);
        $accountant->assignRole('Accountant');

        Sanctum::actingAs($d['teacher']);
        $lessonId = $this->postJson('/api/v1/lessons', [
            'school_class_id' => $d['class']->id, 'title' => 'Lesson', 'video_url' => 'https://youtu.be/x',
        ])->json('data.id');

        Sanctum::actingAs($accountant);
        $this->postJson('/api/v1/lessons', [
            'school_class_id' => $d['class']->id, 'title' => 'Not allowed', 'video_url' => 'https://youtu.be/y',
        ])->assertForbidden();

        Sanctum::actingAs($d['teacher']);
        $this->deleteJson("/api/v1/lessons/{$lessonId}")->assertOk();
        $this->assertSoftDeleted('lessons', ['uuid' => $lessonId]);
    }
}
