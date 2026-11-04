<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\DefaultRoleProvisioner;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** A control number is the student's own, permanent reference — assigned once, reused on every invoice, never changed. */
class StudentControlNumberTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected SchoolClass $class;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
        $this->year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);

        setPermissionsTeamId($this->school->id);
        $this->admin = User::create(['school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $this->admin->assignRole('School Administrator');
    }

    public function test_admission_assigns_a_permanent_numeric_control_number_starting_with_eight(): void
    {
        $student = app(AdmissionService::class)->admit($this->school, [
            'student' => ['first_name' => 'Neema', 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10', 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id],
            'guardians' => [['name' => 'Asha', 'phone' => '0712345678', 'relationship' => 'mother', 'is_primary' => true]],
        ]);

        $this->assertMatchesRegularExpression('/^8\d{9}$/', $student->control_number);

        $second = app(AdmissionService::class)->admit($this->school, [
            'student' => ['first_name' => 'Juma', 'last_name' => 'Ali', 'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10', 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id],
            'guardians' => [['name' => 'Fatma', 'phone' => '0722222222', 'relationship' => 'mother', 'is_primary' => true]],
        ]);

        $this->assertNotSame($student->control_number, $second->control_number);
    }

    public function test_every_invoice_for_the_same_student_carries_the_same_control_number(): void
    {
        $student = app(AdmissionService::class)->admit($this->school, [
            'student' => ['first_name' => 'Neema', 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10', 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id],
            'guardians' => [['name' => 'Asha', 'phone' => '0712345678', 'relationship' => 'mother', 'is_primary' => true]],
        ]);
        $ownControlNumber = $student->control_number;

        $termOne = FeeStructure::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id, 'name' => 'Term 1']);
        $termOne->items()->create(['name' => 'Tuition', 'amount' => 500000]);
        $termTwo = FeeStructure::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id, 'name' => 'Term 2']);
        $termTwo->items()->create(['name' => 'Tuition', 'amount' => 500000]);

        $invoices = app(InvoiceService::class);
        $first = $invoices->generate($this->school, $termOne, collect([$student]), null, null)->first();
        $second = $invoices->generate($this->school, $termTwo, collect([$student]), null, null)->first();

        $this->assertSame($ownControlNumber, $first->control_number);
        $this->assertSame($ownControlNumber, $second->control_number);
        $this->assertNotSame($first->invoice_number, $second->invoice_number, 'the invoice number is still per-invoice');
    }

    public function test_a_student_admitted_before_this_feature_gets_one_lazily_on_first_invoice(): void
    {
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id, 'admission_number' => 'STU-OLD', 'first_name' => 'Old', 'last_name' => 'Timer', 'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2020-01-10']);
        $this->assertNull($student->control_number);

        $structure = FeeStructure::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id, 'name' => 'Term 1']);
        $structure->items()->create(['name' => 'Tuition', 'amount' => 500000]);

        $invoice = app(InvoiceService::class)->generate($this->school, $structure, collect([$student]), null, null)->first();

        $this->assertMatchesRegularExpression('/^8\d{9}$/', $invoice->control_number);
        $this->assertSame($invoice->control_number, $student->fresh()->control_number);
    }

    public function test_a_control_number_cannot_be_changed_once_assigned(): void
    {
        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id, 'admission_number' => 'STU-1', 'control_number' => '8000000001', 'first_name' => 'Neema', 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10']);

        $student->update(['control_number' => '9999999999', 'first_name' => 'Neema']);

        $this->assertSame('8000000001', $student->fresh()->control_number);
        $this->assertSame('Neema', $student->fresh()->first_name, 'other fields still update normally');
    }

    public function test_two_schools_number_their_students_independently(): void
    {
        $schoolB = School::create(['name' => 'Horizon', 'slug' => 'horizon', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($schoolB);
        $yearB = AcademicYear::create(['school_id' => $schoolB->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $classB = SchoolClass::create(['school_id' => $schoolB->id, 'name' => 'Form 1']);

        $a = app(AdmissionService::class)->admit($this->school, [
            'student' => ['first_name' => 'A', 'last_name' => 'One', 'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10', 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id],
            'guardians' => [['name' => 'G', 'phone' => '0712345678', 'relationship' => 'guardian', 'is_primary' => true]],
        ]);
        $b = app(AdmissionService::class)->admit($schoolB, [
            'student' => ['first_name' => 'B', 'last_name' => 'One', 'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10', 'academic_year_id' => $yearB->id, 'school_class_id' => $classB->id],
            'guardians' => [['name' => 'G', 'phone' => '0722222222', 'relationship' => 'guardian', 'is_primary' => true]],
        ]);

        $this->assertSame('8000000001', $a->control_number);
        $this->assertSame('8000000001', $b->control_number, "each school's own sequence starts at 1");
    }
}
