<?php

namespace Tests\Feature\Library;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LibraryTest extends TestCase
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
            'admission_number' => "STU-{$name}-1", 'first_name' => 'John', 'last_name' => 'Doe',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $admin, $student];
    }

    public function test_admin_can_add_a_book_with_available_copies_matching_total(): void
    {
        [, $admin] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/library', [
            'title' => 'Things Fall Apart', 'author' => 'Chinua Achebe', 'total_copies' => 3,
        ])->assertRedirect(route('school-admin.library.index'));

        $this->assertDatabaseHas('books', ['title' => 'Things Fall Apart', 'total_copies' => 3, 'available_copies' => 3]);
    }

    public function test_borrowing_decrements_available_copies_and_creates_a_loan(): void
    {
        [, $admin, $student] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/library', ['title' => 'A Book', 'total_copies' => 1]);
        $book = Book::first();

        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ])->assertRedirect(route('school-admin.library.index'));

        $this->assertSame(0, $book->fresh()->available_copies);
        $this->assertDatabaseHas('book_loans', ['book_id' => $book->id, 'student_id' => $student->id, 'status' => 'borrowed']);
    }

    public function test_borrowing_a_fully_borrowed_book_is_rejected(): void
    {
        [, $admin, $student] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/library', ['title' => 'A Book', 'total_copies' => 1]);
        $book = Book::first();

        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $response = $this->from('/library')->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $response->assertSessionHasErrors('book_id');
        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_returning_on_time_restores_the_copy_with_no_fine(): void
    {
        [, $admin, $student] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/library', ['title' => 'A Book', 'total_copies' => 1]);
        $book = Book::first();
        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ]);
        $loan = BookLoan::first();

        $this->actingAs($admin)->patch("/library/loans/{$loan->uuid}/return")
            ->assertRedirect(route('school-admin.library.loans'));

        $this->assertSame(1, $book->fresh()->available_copies);
        $loan->refresh();
        $this->assertSame('returned', $loan->status);
        $this->assertNull($loan->fine_amount);
    }

    public function test_returning_overdue_computes_a_fine(): void
    {
        [, $admin, $student] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/library', ['title' => 'A Book', 'total_copies' => 1]);
        $book = Book::first();
        // Due today — a real book issue can't be backdated (the "due
        // date must be today or later" rule is correct, so simulate the
        // overdue return by actually traveling forward in time rather
        // than trying to submit a past due_date through the form.
        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->toDateString(),
        ]);
        $loan = BookLoan::first();

        $this->travel(5)->days();
        $this->actingAs($admin)->patch("/library/loans/{$loan->uuid}/return");
        $this->travelBack();

        $loan->refresh();
        $this->assertSame('returned', $loan->status);
        $this->assertEquals(500, $loan->fine_amount); // 5 days overdue * 100/day
    }

    public function test_marking_a_loan_lost_does_not_restore_the_copy(): void
    {
        [, $admin, $student] = $this->makeSchoolWithStudent();

        $this->actingAs($admin)->post('/library', ['title' => 'A Book', 'total_copies' => 1]);
        $book = Book::first();
        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ]);
        $loan = BookLoan::first();

        $this->actingAs($admin)->patch("/library/loans/{$loan->uuid}/lost");

        $this->assertSame(0, $book->fresh()->available_copies);
        $this->assertSame('lost', $loan->fresh()->status);
    }

    public function test_teacher_cannot_manage_the_library(): void
    {
        [$school] = $this->makeSchoolWithStudent();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-lib@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->post('/library', ['title' => 'X', 'total_copies' => 1])->assertForbidden();
    }

    public function test_school_b_cannot_see_school_as_books(): void
    {
        [, $adminA] = $this->makeSchoolWithStudent('School A');
        [, $adminB] = $this->makeSchoolWithStudent('School B');

        $this->actingAs($adminA)->post('/library', ['title' => 'Only In A', 'total_copies' => 1]);
        session()->flush();

        $response = $this->actingAs($adminB)->get('/library');
        $response->assertOk();
        $response->assertDontSee('Only In A');
    }
}
