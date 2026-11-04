<?php

namespace Tests\Feature\Reports;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\BankAccount;
use App\Models\Exam;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSchool(string $name): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        return [$school, $year, $class];
    }

    protected function makeStudent(School $school, $year, SchoolClass $class, string $admissionNumber, string $gender, string $status = 'active'): Student
    {
        return Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => $admissionNumber, 'first_name' => 'S', 'last_name' => 'Test',
            'gender' => $gender, 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => $status,
        ]);
    }

    public function test_enrollment_counts_students_by_gender_and_status_per_class(): void
    {
        [$school, $year, $class] = $this->makeSchool('Sunrise');
        $this->makeStudent($school, $year, $class, 'S1', 'male');
        $this->makeStudent($school, $year, $class, 'S2', 'female');
        $this->makeStudent($school, $year, $class, 'S3', 'male', 'withdrawn');

        $rows = app(ReportService::class)->enrollment($school);
        $row = $rows->firstWhere('class', 'Form 1');

        $this->assertSame(3, $row['total']);
        $this->assertSame(2, $row['male']);
        $this->assertSame(1, $row['female']);
        $this->assertSame(2, $row['active']); // withdrawn student excluded
    }

    public function test_financial_report_sums_invoiced_collected_and_outstanding_per_class(): void
    {
        [$school, $year, $class] = $this->makeSchool('Sunrise');
        $student = $this->makeStudent($school, $year, $class, 'S1', 'male');

        $bank = BankAccount::create([
            'school_id' => $school->id, 'bank_name' => 'Test Bank', 'account_name' => 'School',
            'account_number' => '123',
        ]);
        $structure = FeeStructure::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'school_class_id' => $class->id, 'name' => 'Fees',
        ]);
        $structure->items()->create(['name' => 'Tuition', 'amount' => 100000]);

        $invoice = Invoice::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'academic_year_id' => $year->id, 'bank_account_id' => $bank->id,
            'invoice_number' => 'INV-1', 'control_number' => 'CN-1',
            'total_amount' => 100000, 'amount_paid' => 40000, 'status' => 'partial', 'issued_date' => now(),
        ]);
        Payment::create([
            'school_id' => $school->id, 'invoice_id' => $invoice->id, 'student_id' => $student->id,
            'receipt_number' => 'RCT-1', 'amount' => 40000, 'type' => 'payment', 'method' => 'cash', 'paid_on' => now(),
        ]);

        $rows = app(ReportService::class)->financial($school, null, null);
        $row = $rows->firstWhere('class', 'Form 1');

        $this->assertEquals(100000, $row['invoiced']);
        $this->assertEquals(40000, $row['collected']);
        $this->assertSame('60000.00', $row['outstanding']);
        $this->assertSame(40.0, $row['collection_rate']);
    }

    public function test_attendance_report_computes_rate_from_present_over_total_records(): void
    {
        [$school, $year, $class] = $this->makeSchool('Sunrise');
        $s1 = $this->makeStudent($school, $year, $class, 'S1', 'male');
        $s2 = $this->makeStudent($school, $year, $class, 'S2', 'female');

        AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $s1->id, 'school_class_id' => $class->id, 'date' => '2026-02-01', 'status' => 'present']);
        AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $s2->id, 'school_class_id' => $class->id, 'date' => '2026-02-01', 'status' => 'absent']);
        AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $s1->id, 'school_class_id' => $class->id, 'date' => '2026-02-02', 'status' => 'present']);

        $rows = app(ReportService::class)->attendance($school, '2026-02-01', '2026-02-02');
        $row = $rows->firstWhere('class', 'Form 1');

        $this->assertSame(3, $row['total_records']);
        $this->assertSame(2, $row['present']);
        $this->assertSame(1, $row['absent']);
        $this->assertEqualsWithDelta(66.7, $row['attendance_rate'], 0.1);
    }

    public function test_academic_report_computes_average_and_pass_rate_at_fifty_percent_threshold(): void
    {
        [$school, $year, $class] = $this->makeSchool('Sunrise');
        $s1 = $this->makeStudent($school, $year, $class, 'S1', 'male');
        $s2 = $this->makeStudent($school, $year, $class, 'S2', 'female');

        $subject = Subject::create(['school_id' => $school->id, 'name' => 'Math']);
        $exam = Exam::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id,
            'name' => 'Mid-Term', 'status' => 'draft',
        ]);
        $exam->classes()->attach($class->id);
        $exam->subjects()->attach($subject->id, ['max_marks' => 100]);

        $exam->marks()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'student_id' => $s1->id, 'marks_obtained' => 80]);
        $exam->marks()->create(['school_id' => $school->id, 'subject_id' => $subject->id, 'student_id' => $s2->id, 'marks_obtained' => 30]);

        $rows = app(ReportService::class)->academic($exam);
        $row = $rows->firstWhere('subject', 'Math');

        $this->assertSame(2, $row['entries']);
        $this->assertSame(55.0, $row['average']);
        $this->assertSame(80.0, $row['highest']);
        $this->assertSame(30.0, $row['lowest']);
        $this->assertSame(50.0, $row['pass_rate']); // only the 80-mark entry clears the 50-mark pass threshold
    }

    /**
     * Calling the service directly (as this whole test file does) never
     * goes through IdentifyTenant middleware, so TenantContext is never
     * bound — meaning BelongsToTenant's automatic global scope does NOT
     * filter anything here. Every report method must filter by the given
     * $school explicitly rather than assume ambient tenant scoping is
     * active; this test would fail (leak School B's class into School
     * A's report) if that explicit filtering were ever removed.
     */
    public function test_reports_do_not_leak_another_schools_data_even_without_tenant_context_bound(): void
    {
        [$schoolA, $yearA, $classA] = $this->makeSchool('School A');
        [$schoolB, $yearB, $classB] = $this->makeSchool('School B');
        $this->makeStudent($schoolA, $yearA, $classA, 'A1', 'male');
        $this->makeStudent($schoolB, $yearB, $classB, 'B1', 'male');

        $rows = app(ReportService::class)->enrollment($schoolA);

        $this->assertCount(1, $rows);
        $this->assertSame('Form 1', $rows->first()['class']);
        $this->assertSame(1, $rows->first()['total']);
    }
}
