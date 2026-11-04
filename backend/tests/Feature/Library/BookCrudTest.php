<?php

namespace Tests\Feature\Library;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookCrudTest extends TestCase
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

    public function test_admin_can_update_total_copies_and_available_copies_shifts_with_it(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/library', ['title' => 'Things Fall Apart', 'total_copies' => 2]);
        $book = Book::first();
        $this->assertSame(2, $book->available_copies);

        $this->actingAs($admin)->put("/library/{$book->uuid}", ['title' => 'Things Fall Apart', 'total_copies' => 5])
            ->assertRedirect(route('school-admin.library.index'));
        $this->assertSame(5, $book->fresh()->total_copies);
        $this->assertSame(5, $book->fresh()->available_copies);
    }

    public function test_reducing_total_copies_below_what_is_on_loan_is_rejected(): void
    {
        [, $student, $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/library', ['title' => 'Things Fall Apart', 'total_copies' => 1]);
        $book = Book::first();

        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $response = $this->from('/library')->actingAs($admin)->put("/library/{$book->uuid}", [
            'title' => 'Things Fall Apart', 'total_copies' => 0,
        ]);

        $response->assertSessionHasErrors('total_copies');
        $this->assertSame(1, $book->fresh()->total_copies);
    }

    public function test_deleting_a_book_currently_on_loan_is_blocked(): void
    {
        [, $student, $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/library', ['title' => 'Things Fall Apart', 'total_copies' => 1]);
        $book = Book::first();
        $this->actingAs($admin)->post("/library/{$book->uuid}/borrow", [
            'student_id' => $student->id, 'due_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->actingAs($admin)->delete("/library/{$book->uuid}")->assertSessionHasErrors('book');
        $this->assertNotSoftDeleted($book);
    }

    public function test_admin_can_delete_a_book_with_no_active_loans(): void
    {
        [, , $admin] = $this->makeSchoolWithStudent();
        $this->actingAs($admin)->post('/library', ['title' => 'Things Fall Apart', 'total_copies' => 1]);
        $book = Book::first();

        $this->actingAs($admin)->delete("/library/{$book->uuid}")
            ->assertRedirect(route('school-admin.library.index'));
        $this->assertSoftDeleted($book);
    }
}
