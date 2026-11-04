<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\FeeStructure;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Create / edit / delete for the modules the mobile app gained: staff, inventory, library, hostel, terms, subjects, fee structures. */
class MobileModulesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
    }

    protected function user(string $name, string $role): User
    {
        setPermissionsTeamId($this->school->id);
        $user = User::create(['school_id' => $this->school->id, 'name' => $name, 'email' => str($name)->slug().'@test.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    protected function admin(): User
    {
        $admin = $this->user('Admin', 'School Administrator');
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function student(): Student
    {
        $year = AcademicYear::firstOrCreate(['school_id' => $this->school->id, 'name' => '2026'], ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::firstOrCreate(['school_id' => $this->school->id, 'name' => 'Form 1']);

        return Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'S-'.random_int(1000, 9999), 'first_name' => 'Kid', 'last_name' => 'One', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15'])->refresh();
    }

    // ---- Staff -------------------------------------------------------------------------------------------------

    public function test_staff_can_be_onboarded_listed_edited_and_suspended(): void
    {
        $admin = $this->admin();

        $created = $this->postJson('/api/v1/staff', [
            'user' => ['name' => 'Amina Juma', 'email' => 'amina@test.local', 'phone' => '0712345678', 'role' => 'Teacher'],
            'profile' => ['job_title' => 'Maths teacher', 'employment_type' => 'full_time', 'date_joined' => '2026-01-05'],
        ])->assertCreated();

        $created->assertJsonPath('data.name', 'Amina Juma')->assertJsonPath('data.roles.0', 'Teacher')->assertJsonPath('data.profile.job_title', 'Maths teacher');
        // Their first password is their own phone number — easy to
        // remember, nothing random to copy down (login itself is verified
        // in test_a_staff_members_first_password_is_their_own_phone_number,
        // separately — logging in mid-test here would clobber this test's
        // own Sanctum::actingAs() admin session for the requests below).
        $this->assertSame('0712345678', $created->json('temporary_password'));
        $this->assertStringStartsWith('EMP-', $created->json('data.profile.employee_number'));
        $uuid = $created->json('data.id');

        $this->getJson('/api/v1/staff?search=Amina')->assertOk()->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/staff/$uuid", ['job_title' => 'Head of Maths', 'employment_type' => 'contract', 'date_joined' => '2026-01-05', 'phone' => '0799999999'])
            ->assertOk()->assertJsonPath('data.profile.job_title', 'Head of Maths')->assertJsonPath('data.phone', '0799999999');

        // Reassigning a staff member's role — replaces it, doesn't add to it.
        $this->putJson("/api/v1/staff/$uuid", ['role' => 'HR', 'employment_type' => 'contract', 'date_joined' => '2026-01-05'])
            ->assertOk()->assertJsonPath('data.roles.0', 'HR')->assertJsonCount(1, 'data.roles');

        $this->patchJson("/api/v1/staff/$uuid/status", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->patchJson("/api/v1/staff/{$admin->uuid}/status", ['status' => 'suspended'])->assertStatus(404); // the admin has no staff profile → not listed
    }

    public function test_a_staff_members_first_password_is_their_own_phone_number(): void
    {
        $this->admin();

        $this->postJson('/api/v1/staff', [
            'user' => ['name' => 'Amina Juma', 'email' => 'amina.login@test.local', 'phone' => '0712345678', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-05'],
        ])->assertCreated();

        // Fresh guard — Sanctum::actingAs()'s fake "current user" shortcut
        // and a real /auth/login call don't mix within one test otherwise.
        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');
        $this->postJson('/api/v1/auth/login', ['email' => 'amina.login@test.local', 'password' => '0712345678', 'device_name' => 't'])->assertOk();
    }

    public function test_a_phone_number_is_required_to_onboard_staff_since_it_becomes_their_password(): void
    {
        $this->admin();

        $this->postJson('/api/v1/staff', [
            'user' => ['name' => 'No Phone', 'email' => 'nophone@test.local', 'role' => 'Teacher'],
            'profile' => ['employment_type' => 'full_time', 'date_joined' => '2026-01-05'],
        ])->assertUnprocessable()->assertJsonValidationErrors('user.phone');
    }

    public function test_staff_cannot_reassign_their_own_role_over_the_api(): void
    {
        $admin = $this->admin();
        \App\Models\StaffProfile::create([
            'school_id' => $this->school->id, 'user_id' => $admin->id, 'employee_number' => 'EMP-SELF',
            'employment_type' => 'full_time', 'date_joined' => '2026-01-01',
        ]);

        $this->putJson("/api/v1/staff/{$admin->uuid}", ['role' => 'Teacher', 'employment_type' => 'full_time', 'date_joined' => '2026-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        setPermissionsTeamId($this->school->id);
        $this->assertTrue($admin->fresh()->hasRole('School Administrator'));
    }

    public function test_salary_is_only_shown_to_people_who_can_see_payroll(): void
    {
        $admin = $this->admin();
        $teacher = $this->user('Teacher', 'Teacher');
        \App\Models\StaffProfile::create(['school_id' => $this->school->id, 'user_id' => $teacher->id, 'employee_number' => 'EMP-1', 'date_joined' => '2025-01-01', 'employment_type' => 'full_time', 'basic_salary' => 900000]);

        $this->getJson('/api/v1/staff')->assertOk()->assertJsonPath('data.0.profile.basic_salary', '900000.00');

        // Someone who may see staff records but not payroll (HR itself holds payroll.view, so use a bare permission).
        $viewer = $this->user('Records Clerk', 'Teacher');
        setPermissionsTeamId($this->school->id);
        $viewer->givePermissionTo('hr.view');
        Sanctum::actingAs($viewer->fresh());
        $this->getJson('/api/v1/staff')->assertOk()->assertJsonPath('data.0.profile.basic_salary', null);

        Sanctum::actingAs($this->user('Other Teacher', 'Teacher'));
        $this->getJson('/api/v1/staff')->assertForbidden();
    }

    // ---- Inventory ------------------------------------------------------------------------------------------------

    public function test_inventory_items_stock_movements_and_categories(): void
    {
        $this->admin();
        $cat = $this->postJson('/api/v1/inventory/categories', ['name' => 'Stationery'])->assertCreated()->json('data.id');

        $item = $this->postJson('/api/v1/inventory/items', ['name' => 'Chalk', 'unit' => 'box', 'inventory_category_id' => $cat, 'reorder_level' => 5, 'opening_quantity' => 10])
            ->assertCreated()->assertJsonPath('data.quantity_on_hand', '10.00')->assertJsonPath('data.category', 'Stationery')->assertJsonPath('data.low_stock', false)->json('data.id');

        $this->postJson('/api/v1/inventory/movements', ['inventory_item_id' => $item, 'type' => 'out', 'quantity' => 7, 'reason' => 'Term 3', 'moved_at' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.quantity_on_hand', '3.00')->assertJsonPath('data.low_stock', true);

        $this->getJson("/api/v1/inventory/items/$item/movements")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/inventory/items?low_stock=1')->assertOk()->assertJsonCount(1, 'data');

        // Cannot take out more than is on hand, and cannot delete an item that still has stock.
        $this->postJson('/api/v1/inventory/movements', ['inventory_item_id' => $item, 'type' => 'out', 'quantity' => 50, 'moved_at' => now()->toDateString()])->assertUnprocessable();
        $this->deleteJson("/api/v1/inventory/items/$item")->assertUnprocessable();

        $this->putJson("/api/v1/inventory/items/$item", ['name' => 'White chalk', 'unit' => 'box'])->assertOk()->assertJsonPath('data.name', 'White chalk');

        $this->postJson('/api/v1/inventory/movements', ['inventory_item_id' => $item, 'type' => 'out', 'quantity' => 3, 'moved_at' => now()->toDateString()])->assertCreated();
        $this->deleteJson("/api/v1/inventory/items/$item")->assertOk();
        $this->assertNull(InventoryItem::find($item));
        $this->assertSame(1, InventoryCategory::count());
    }

    // ---- Library --------------------------------------------------------------------------------------------------------

    public function test_books_can_be_catalogued_borrowed_by_a_student_uuid_and_returned(): void
    {
        $this->admin();
        $student = $this->student();

        $book = $this->postJson('/api/v1/library/books', ['title' => 'Kiswahili Sanifu', 'author' => 'TUKI', 'total_copies' => 2])->assertCreated()->assertJsonPath('data.available_copies', 2)->json('data.id');

        $loan = $this->postJson("/api/v1/library/books/$book/borrow", ['student_id' => $student->uuid, 'due_date' => now()->addWeek()->toDateString()])
            ->assertCreated()->assertJsonPath('data.status', 'borrowed')->assertJsonPath('data.student', 'Kid One')->json('data.id');

        $this->getJson('/api/v1/library/books')->assertOk()->assertJsonPath('data.0.available_copies', 1);
        $this->deleteJson("/api/v1/library/books/$book")->assertUnprocessable(); // a copy is out

        $this->patchJson("/api/v1/library/loans/$loan/return")->assertOk()->assertJsonPath('data.status', 'returned');
        $this->getJson('/api/v1/library/books')->assertJsonPath('data.0.available_copies', 2);
        $this->getJson('/api/v1/library/loans?status=returned')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/library/books/$book", ['title' => 'Kamusi', 'total_copies' => 3])->assertOk()->assertJsonPath('data.total_copies', 3);
        $this->deleteJson("/api/v1/library/books/$book")->assertOk();
        $this->assertNull(Book::find($book));
    }

    // ---- Hostel ------------------------------------------------------------------------------------------------------------

    public function test_hostels_rooms_and_allocations_with_a_warden_and_student_by_uuid(): void
    {
        $this->admin();
        $student = $this->student();
        $warden = $this->user('Warden', 'Warden');

        $hostel = $this->postJson('/api/v1/hostel/hostels', ['name' => 'Baobab', 'gender' => 'male', 'warden_user_id' => $warden->uuid])
            ->assertCreated()->assertJsonPath('data.warden.name', 'Warden')->json('data.id');

        $room = $this->postJson('/api/v1/hostel/rooms', ['hostel_id' => $hostel, 'room_number' => 'A1', 'capacity' => 2])->assertCreated()->json('data.id');

        $allocation = $this->postJson("/api/v1/hostel/rooms/$room/allocate", ['student_id' => $student->uuid])
            ->assertCreated()->assertJsonPath('data.student', 'Kid One')->assertJsonPath('data.room', 'Baobab · A1')->json('data.id');

        $this->getJson('/api/v1/hostel/hostels')->assertOk()->assertJsonPath('data.0.occupied', 1)->assertJsonPath('data.0.capacity', 2);
        $this->deleteJson("/api/v1/hostel/rooms/$room")->assertUnprocessable();
        $this->deleteJson("/api/v1/hostel/hostels/$hostel")->assertUnprocessable();

        $this->patchJson("/api/v1/hostel/allocations/$allocation/vacate")->assertOk()->assertJsonPath('data.status', 'vacated');
        $this->deleteJson("/api/v1/hostel/rooms/$room")->assertOk();
        $this->deleteJson("/api/v1/hostel/hostels/$hostel")->assertOk();
        $this->assertNull(Hostel::find($hostel));
        $this->assertNull(HostelRoom::find($room));
    }

    // ---- Terms / subjects / classes / streams ---------------------------------------------------------------------------------

    public function test_terms_follow_the_four_per_year_rule_and_current_is_exclusive(): void
    {
        $this->admin();
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);

        $first = $this->postJson('/api/v1/terms', ['academic_year_id' => $year->id, 'name' => 'Term 1', 'start_date' => '2026-01-05', 'end_date' => '2026-04-03', 'is_current' => true])->assertCreated()->json('data.id');
        $second = $this->postJson('/api/v1/terms', ['academic_year_id' => $year->id, 'name' => 'Term 2', 'start_date' => '2026-05-01', 'end_date' => '2026-08-01', 'is_current' => true])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/terms')->assertOk()->assertJsonCount(2, 'data');
        $this->assertFalse((bool) \App\Models\Term::find($first)->is_current, 'making Term 2 current unsets Term 1');

        $this->postJson('/api/v1/terms', ['academic_year_id' => $year->id, 'name' => 'Term 2', 'start_date' => '2026-09-01', 'end_date' => '2026-10-01'])->assertUnprocessable();
        $this->postJson('/api/v1/terms', ['academic_year_id' => $year->id, 'name' => 'Term 3', 'start_date' => '2026-09-01', 'end_date' => '2026-10-01'])->assertCreated();
        $this->postJson('/api/v1/terms', ['academic_year_id' => $year->id, 'name' => 'Term 4', 'start_date' => '2026-10-02', 'end_date' => '2026-11-01'])->assertCreated();
        $this->postJson('/api/v1/terms', ['academic_year_id' => $year->id, 'name' => 'Term 5', 'start_date' => '2026-11-02', 'end_date' => '2026-12-01'])->assertUnprocessable();

        $this->putJson("/api/v1/terms/$second", ['name' => 'Second term', 'start_date' => '2026-05-01', 'end_date' => '2026-08-01'])->assertOk()->assertJsonPath('data.name', 'Second term');
        $this->deleteJson("/api/v1/terms/$second")->assertOk();
    }

    public function test_subjects_can_be_edited_and_deleted_unless_they_have_exam_records(): void
    {
        $this->admin();
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Maths']);

        $this->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'Mathematics', 'code' => 'MAT', 'school_class_ids' => [$class->id]])
            ->assertOk()->assertJsonPath('data.name', 'Mathematics');
        $this->assertSame([$class->id], $subject->classes()->pluck('school_classes.id')->all());

        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $exam = \App\Models\Exam::create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => 'Mid']);
        $exam->classes()->attach($class->id);
        \Illuminate\Support\Facades\DB::table('exam_subjects')->insert(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'max_marks' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $this->deleteJson("/api/v1/subjects/{$subject->id}")->assertUnprocessable();

        $other = Subject::create(['school_id' => $this->school->id, 'name' => 'Art']);
        $this->deleteJson("/api/v1/subjects/{$other->id}")->assertOk();
    }

    public function test_classes_and_streams_cannot_be_deleted_while_students_are_in_them(): void
    {
        $this->admin();
        $student = $this->student();
        $stream = Stream::create(['school_id' => $this->school->id, 'school_class_id' => $student->school_class_id, 'name' => 'Blue']);
        $student->update(['stream_id' => $stream->id]);

        $this->deleteJson("/api/v1/streams/{$stream->id}")->assertUnprocessable();
        $this->deleteJson("/api/v1/classes/{$student->school_class_id}")->assertUnprocessable();

        $student->update(['stream_id' => null]);
        $this->deleteJson("/api/v1/streams/{$stream->id}")->assertOk();

        $empty = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 9']);
        $this->deleteJson("/api/v1/classes/{$empty->id}")->assertOk();
    }

    // ---- Fee structures -------------------------------------------------------------------------------------------------------------

    public function test_a_fee_structure_can_be_edited_and_deleted_until_invoices_exist(): void
    {
        $this->admin();
        $student = $this->student();
        $year = AcademicYear::first();

        $created = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1 fees', 'academic_year_id' => $year->id, 'school_class_id' => $student->school_class_id,
            'items' => [['name' => 'Tuition', 'amount' => 500000], ['name' => 'Uniform', 'amount' => 60000]],
        ])->assertCreated();
        $id = $created->json('data.id');

        $this->putJson("/api/v1/fee-structures/$id", ['name' => 'Form 1 fees 2026', 'items' => [['name' => 'Tuition', 'amount' => 520000]]])
            ->assertOk()->assertJsonPath('data.name', 'Form 1 fees 2026')->assertJsonCount(1, 'data.items');

        $structure = FeeStructure::findOrFail($id);
        Invoice::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-1', 'control_number' => 'CN-1', 'total_amount' => 520000, 'issued_date' => now()->toDateString()]);

        $this->deleteJson("/api/v1/fee-structures/$id")->assertUnprocessable();
    }

    public function test_certificates_id_cards_and_invoices_accept_student_uuids(): void
    {
        $this->admin();
        $student = $this->student();
        $year = AcademicYear::first();

        $this->postJson('/api/v1/certificates', ['student_id' => $student->uuid, 'title' => 'Best in Maths', 'issued_date' => now()->toDateString()])
            ->assertCreated();
        $this->postJson('/api/v1/id-cards', ['student_id' => $student->uuid, 'issued_date' => now()->toDateString()])
            ->assertCreated();

        $structure = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1', 'academic_year_id' => $year->id, 'school_class_id' => $student->school_class_id, 'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/fee-structures/$structure/invoices", ['student_ids' => [$student->uuid], 'due_date' => now()->addMonth()->toDateString()])
            ->assertSuccessful();
        $this->assertSame(1, Invoice::where('student_id', $student->id)->count());
    }

    public function test_people_without_the_permission_cannot_touch_these_modules(): void
    {
        $this->admin();
        $teacher = $this->user('Teacher', 'Teacher');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/staff', [])->assertForbidden();
        $this->postJson('/api/v1/inventory/items', ['name' => 'X', 'unit' => 'u'])->assertForbidden();
        $this->postJson('/api/v1/library/books', ['title' => 'X', 'total_copies' => 1])->assertForbidden();
        $this->postJson('/api/v1/hostel/hostels', ['name' => 'X', 'gender' => 'male'])->assertForbidden();
        $this->postJson('/api/v1/terms', [])->assertForbidden();
    }
}
