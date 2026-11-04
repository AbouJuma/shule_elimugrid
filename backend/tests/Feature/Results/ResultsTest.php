<?php

namespace Tests\Feature\Results;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use App\Services\ReportCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeSchool(string $name = 'Sunrise'): array
    {
        $school = School::create(['name' => $name, 'slug' => str($name)->slug(), 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);
        $term = Term::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'Term 1',
            'start_date' => '2026-01-05', 'end_date' => '2026-04-01', 'is_current' => true,
        ]);

        setPermissionsTeamId($school->id);
        $admin = User::create([
            'school_id' => $school->id, 'name' => "{$name} Admin",
            'email' => str($name)->slug().'-admin@test.local', 'password' => 'password', 'status' => 'active',
        ]);
        $admin->assignRole('School Administrator');

        return [$school, $year, $class, $term, $admin];
    }

    protected function student(School $school, SchoolClass $class, AcademicYear $year, int $n): Student
    {
        return Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-{$n}", 'first_name' => "Student{$n}", 'last_name' => 'Test',
            'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15', 'status' => 'active',
        ]);
    }

    /** Two exams (out of 100 and 50) in Math and English for three students. */
    protected function seedResults(): array
    {
        [$school, $year, $class, $term, $admin] = $this->makeSchool();
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $eng = Subject::create(['school_id' => $school->id, 'name' => 'English']);

        $mid = Exam::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Midterm']);
        $end = Exam::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'End term']);
        foreach ([$mid, $end] as $exam) {
            $exam->classes()->attach($class->id);
        }
        foreach ([$mid, $end] as $exam) {
            $max = $exam->id === $mid->id ? 100 : 50;
            DB::table('exam_subjects')->insert([
                ['exam_id' => $exam->id, 'subject_id' => $math->id, 'max_marks' => $max, 'created_at' => now(), 'updated_at' => now()],
                ['exam_id' => $exam->id, 'subject_id' => $eng->id, 'max_marks' => $max, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $a = $this->student($school, $class, $year, 1);
        $b = $this->student($school, $class, $year, 2);
        $c = $this->student($school, $class, $year, 3);

        // A: Math 80% & 40/50=80% -> 80 ; English 60 & 30/50=60 -> 60 ; average 70
        // B: same as A                                            -> average 70 (tie for 1st)
        // C: Math 40 & 20/50=40 -> 40 ; English 20 & 10/50=20 -> 20 ; average 30
        $rows = [
            [$a, $math, $mid, 80], [$a, $math, $end, 40], [$a, $eng, $mid, 60], [$a, $eng, $end, 30],
            [$b, $math, $mid, 80], [$b, $math, $end, 40], [$b, $eng, $mid, 60], [$b, $eng, $end, 30],
            [$c, $math, $mid, 40], [$c, $math, $end, 20], [$c, $eng, $mid, 20], [$c, $eng, $end, 10],
        ];
        foreach ($rows as [$student, $subject, $exam, $marks]) {
            ExamMark::create([
                'school_id' => $school->id, 'exam_id' => $exam->id, 'subject_id' => $subject->id,
                'student_id' => $student->id, 'marks_obtained' => $marks,
            ]);
        }

        return [$school, $class, $term, $admin, [$a, $b, $c]];
    }

    public function test_report_cards_average_across_exams_rank_with_ties_and_grade(): void
    {
        [$school, $class, $term, , [$a, $b, $c]] = $this->seedResults();

        $report = app(ReportCardService::class)->forClass($school, $class, $term);
        $cards = $report['cards']->keyBy(fn ($card) => $card['student']->id);

        $this->assertSame(3, $report['class_size']);

        $this->assertEquals(70.0, $cards[$a->id]['average']);
        $this->assertEquals(140.0, $cards[$a->id]['total']);
        $this->assertSame('B', $cards[$a->id]['grade']); // 65-74
        $this->assertSame(1, $cards[$a->id]['position']);
        $this->assertSame(1, $cards[$b->id]['position']); // tie shares position
        $this->assertSame(3, $cards[$c->id]['position']); // next position skips
        $this->assertSame('D', $cards[$c->id]['grade']);   // 30 average

        $mathLine = $cards[$a->id]['lines']->firstWhere('subject', 'Mathematics');
        $this->assertEquals(80.0, $mathLine['percent']);
        $this->assertEquals(66.7, $mathLine['class_average']); // (80+80+40)/3
        $this->assertSame(1, $mathLine['position']);
    }

    public function test_each_line_carries_the_score_in_every_individual_exam(): void
    {
        [$school, $class, $term, , [$a]] = $this->seedResults();

        $report = app(ReportCardService::class)->forClass($school, $class, $term);
        $line = $report['cards']->first(fn ($c) => $c['student']->id === $a->id)['lines']->firstWhere('subject', 'Mathematics');

        // Midterm 80/100 = 80%, End term 40/50 = 80% — keyed by exam id.
        $this->assertSame([80.0, 80.0], array_values($line['by_exam']));
        $this->assertCount(2, $report['exams']);
    }

    public function test_the_pdf_and_api_show_the_per_exam_columns(): void
    {
        [$school, $class, $term, $admin, [$a]] = $this->seedResults();

        $this->actingAs($admin)->get(route('school-admin.results.student-pdf', [$a, 'term_id' => $term->id]))->assertOk();

        \Laravel\Sanctum\Sanctum::actingAs($admin);
        $json = $this->getJson("/api/v1/results?term_id={$term->id}&school_class_id={$class->id}")->assertOk();
        $json->assertJsonCount(2, 'data.exams')->assertJsonPath('data.exams.0.name', 'Midterm');

        $lines = collect($json->json('data.students'))->firstWhere('name', 'Student1 Test')['lines'];
        $this->assertEquals([80, 80], collect($lines)->firstWhere('subject', 'Mathematics')['exam_scores']);
    }

    public function test_a_swahili_remark_is_used_in_swahili_and_falls_back_to_english(): void
    {
        [$school, $class, $term, $admin] = $this->seedResults();
        $scale = app(\App\Services\GradingService::class)->scaleFor($school);

        app(\App\Services\GradingService::class)->replaceScale($school, [
            ['grade' => 'A', 'min_percent' => 75, 'remark' => 'Excellent', 'remark_sw' => 'Bora sana'],
            ['grade' => 'B', 'min_percent' => 50, 'remark' => 'Good', 'remark_sw' => null],
            ['grade' => 'F', 'min_percent' => 0, 'remark' => 'Fail'],
        ]);
        $scale = app(\App\Services\GradingService::class)->scaleFor($school);
        $grading = app(\App\Services\GradingService::class);

        app()->setLocale('en');
        $this->assertSame('Excellent', $grading->gradeFor($scale, 90)['remark']);

        app()->setLocale('sw');
        $this->assertSame('Bora sana', $grading->gradeFor($scale, 90)['remark']);
        $this->assertSame('Good', $grading->gradeFor($scale, 60)['remark'], 'no Swahili wording → English');
    }

    public function test_exam_weights_change_the_term_score(): void
    {
        [$school, $year, $class, $term] = $this->makeSchool();
        $math = Subject::create(['school_id' => $school->id, 'name' => 'Mathematics']);
        $student = $this->student($school, $class, $year, 1);

        $quiz = Exam::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Quiz', 'weight' => 1]);
        $final = Exam::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Final', 'weight' => 3]);
        foreach ([$quiz, $final] as $exam) {
            $exam->classes()->attach($class->id);
            DB::table('exam_subjects')->insert(['exam_id' => $exam->id, 'subject_id' => $math->id, 'max_marks' => 100, 'created_at' => now(), 'updated_at' => now()]);
        }
        ExamMark::create(['school_id' => $school->id, 'exam_id' => $quiz->id, 'subject_id' => $math->id, 'student_id' => $student->id, 'marks_obtained' => 100]);
        ExamMark::create(['school_id' => $school->id, 'exam_id' => $final->id, 'subject_id' => $math->id, 'student_id' => $student->id, 'marks_obtained' => 20]);

        $card = app(ReportCardService::class)->forClass($school, $class, $term)['cards']->first();

        // (100*1 + 20*3) / 4 = 40 — a plain average would have said 60.
        $this->assertEquals(40.0, $card['lines']->first()['percent']);
    }

    public function test_attendance_is_summarised_for_the_term_only(): void
    {
        [$school, $year, $class, $term] = $this->makeSchool();
        $student = $this->student($school, $class, $year, 1);

        foreach ([['2026-02-02', 'present'], ['2026-02-03', 'present'], ['2026-02-04', 'late'], ['2026-02-05', 'absent'], ['2026-02-06', 'excused'], ['2026-08-01', 'absent']] as [$date, $status]) {
            AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $student->id, 'school_class_id' => $class->id, 'date' => $date, 'status' => $status]);
        }

        $attendance = app(ReportCardService::class)->forClass($school, $class, $term)['cards']->first()['attendance'];

        $this->assertSame(5, $attendance['total']);           // the August record is outside Term 1
        $this->assertSame(2, $attendance['present']);
        $this->assertSame(1, $attendance['late']);
        $this->assertSame(1, $attendance['absent']);
        $this->assertSame(1, $attendance['excused']);
        $this->assertEquals(75.0, $attendance['rate']);        // 3 attended / 4 counted (excused excluded)
    }

    public function test_report_cards_do_not_leak_another_schools_data(): void
    {
        [, , , $term] = $this->seedResults();
        [$schoolB, , $classB, $termB] = $this->makeSchool('Other School');

        $reportB = app(ReportCardService::class)->forClass($schoolB, $classB, $termB);

        $this->assertSame(0, $reportB['class_size']);
        $this->assertTrue($reportB['exams']->isEmpty());
    }

    public function test_results_page_lists_students_ranked_and_pdfs_render(): void
    {
        [, $class, $term, $admin, [$a]] = $this->seedResults();

        $this->actingAs($admin)->get("/results?term_id={$term->id}&school_class_id={$class->id}")
            ->assertOk()->assertSee('Student1 Test')->assertSee('Mathematics');

        $single = $this->actingAs($admin)->get("/results/students/{$a->uuid}/pdf?term_id={$term->id}");
        $single->assertOk();
        $this->assertSame('application/pdf', $single->headers->get('content-type'));
        $this->assertPdfPageCount(1, $single->getContent());

        $bundle = $this->actingAs($admin)->get("/results/class-pdf?term_id={$term->id}&school_class_id={$class->id}");
        $bundle->assertOk();
        $this->assertSame('application/pdf', $bundle->headers->get('content-type'));
        $this->assertPdfPageCount(3, $bundle->getContent());
    }

    /** The PDF's own /Count entry — the authoritative page count (libmagic's guess is unreliable for dompdf output). */
    protected function assertPdfPageCount(int $expected, string $pdf): void
    {
        preg_match_all('#/Count (\d+)#', $pdf, $m);
        $this->assertSame($expected, (int) max($m[1]), 'PDF page count');
    }

    public function test_remarks_are_saved_and_appear_in_the_report(): void
    {
        [, $class, $term, $admin, [$a]] = $this->seedResults();

        $this->actingAs($admin)->post('/results/remarks', [
            'term_id' => $term->id, 'school_class_id' => $class->id,
            'remarks' => [$a->id => ['class_teacher_remark' => 'Works hard', 'head_teacher_remark' => 'Keep it up']],
        ])->assertRedirect();

        $this->assertDatabaseHas('report_card_remarks', ['student_id' => $a->id, 'class_teacher_remark' => 'Works hard']);
    }

    public function test_grade_scale_can_be_replaced_but_must_start_at_zero(): void
    {
        [, , , $admin] = $this->seedResults();

        $this->from('/results')->actingAs($admin)->put('/results/grade-scale', [
            'scale' => [['grade' => 'P', 'min_percent' => 50], ['grade' => 'F', 'min_percent' => 10]],
        ])->assertSessionHasErrors('scale');

        $this->actingAs($admin)->put('/results/grade-scale', [
            'scale' => [['grade' => 'P', 'min_percent' => 50, 'remark' => 'Pass'], ['grade' => 'F', 'min_percent' => 0, 'remark' => 'Fail']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grade_scales', ['grade' => 'P', 'min_percent' => 50]);
        $this->assertDatabaseMissing('grade_scales', ['grade' => 'A']);
    }

    public function test_a_year_cannot_have_more_than_four_terms(): void
    {
        [, $year, , , $admin] = $this->makeSchool();

        foreach (['Term 2', 'Term 3', 'Term 4'] as $i => $name) {
            $this->actingAs($admin)->post('/terms', [
                'academic_year_id' => $year->id, 'name' => $name,
                'start_date' => '2026-05-0'.($i + 1), 'end_date' => '2026-06-0'.($i + 1),
            ])->assertSessionHasNoErrors();
        }

        $this->from('/terms')->actingAs($admin)->post('/terms', [
            'academic_year_id' => $year->id, 'name' => 'Term 5', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01',
        ])->assertSessionHasErrors('name');

        $this->assertSame(4, Term::count());
    }

    public function test_terms_can_be_updated_and_deleted_unless_they_have_exams(): void
    {
        [$school, $year, $class, $term, $admin] = $this->makeSchool();

        $this->actingAs($admin)->put("/terms/{$term->id}", [
            'name' => 'First Term', 'start_date' => '2026-01-05', 'end_date' => '2026-04-01', 'is_current' => '1',
        ])->assertRedirect(route('school-admin.terms.index'));
        $this->assertSame('First Term', $term->fresh()->name);

        Exam::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Mid']);
        $this->actingAs($admin)->delete("/terms/{$term->id}")->assertSessionHasErrors('term');
        $this->assertNotSoftDeleted($term);
    }

    public function test_teacher_can_view_but_not_manage_results(): void
    {
        [$school, , $class, $term] = $this->makeSchool();

        setPermissionsTeamId($school->id);
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Teacher', 'email' => 'teacher-results@test.local',
            'password' => 'password', 'status' => 'active',
        ]);
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get("/results?term_id={$term->id}&school_class_id={$class->id}")->assertOk();
        $this->actingAs($teacher)->put('/results/grade-scale', ['scale' => []])->assertForbidden();
    }
}
