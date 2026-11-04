<?php

namespace App\Http\Controllers\Web;

use App\Models\Exam;
use App\Services\ReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends \App\Http\Controllers\Controller
{
    public function index()
    {
        $this->authorize('reports.view');

        return view('school-admin.reports.index');
    }

    public function enrollment(Request $request, TenantContext $tenant, ReportService $reports)
    {
        $this->authorize('reports.view');

        $rows = $reports->enrollment($tenant->assertBound());

        if ($request->string('export')->toString() === 'csv') {
            return $this->csv('enrollment-report.csv', ['Class', 'Total', 'Male', 'Female', 'Active'], $rows->map(fn ($r) => [
                $r['class'], $r['total'], $r['male'], $r['female'], $r['active'],
            ]));
        }

        return view('school-admin.reports.enrollment', ['rows' => $rows]);
    }

    public function financial(Request $request, TenantContext $tenant, ReportService $reports)
    {
        $this->authorize('reports.view');

        $from = $request->string('from')->isNotEmpty() ? $request->string('from')->toString() : null;
        $to = $request->string('to')->isNotEmpty() ? $request->string('to')->toString() : null;
        $rows = $reports->financial($tenant->assertBound(), $from, $to);

        if ($request->string('export')->toString() === 'csv') {
            return $this->csv('financial-report.csv', ['Class', 'Invoiced', 'Collected', 'Outstanding', 'Collection Rate %'], $rows->map(fn ($r) => [
                $r['class'], $r['invoiced'], $r['collected'], $r['outstanding'], $r['collection_rate'],
            ]));
        }

        return view('school-admin.reports.financial', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    public function attendance(Request $request, TenantContext $tenant, ReportService $reports)
    {
        $this->authorize('reports.view');

        $from = $request->string('from')->isNotEmpty() ? $request->string('from')->toString() : now()->startOfMonth()->toDateString();
        $to = $request->string('to')->isNotEmpty() ? $request->string('to')->toString() : now()->toDateString();
        $rows = $reports->attendance($tenant->assertBound(), $from, $to);

        if ($request->string('export')->toString() === 'csv') {
            return $this->csv('attendance-report.csv', ['Class', 'Total Records', 'Present', 'Absent', 'Late', 'Excused', 'Attendance Rate %'], $rows->map(fn ($r) => [
                $r['class'], $r['total_records'], $r['present'], $r['absent'], $r['late'], $r['excused'], $r['attendance_rate'],
            ]));
        }

        return view('school-admin.reports.attendance', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    public function academic(Request $request, ReportService $reports)
    {
        $this->authorize('reports.view');

        $exams = Exam::query()->with('classes')->latest()->get();
        $selectedExamId = $request->integer('exam_id') ?: $exams->first()?->id;
        $exam = $selectedExamId ? $exams->firstWhere('id', $selectedExamId) : null;
        $rows = $exam ? $reports->academic($exam) : collect();

        if ($request->string('export')->toString() === 'csv' && $exam) {
            return $this->csv("academic-report-{$exam->id}.csv", ['Subject', 'Max Marks', 'Entries', 'Average', 'Highest', 'Lowest', 'Pass Rate %'], $rows->map(fn ($r) => [
                $r['subject'], $r['max_marks'], $r['entries'], $r['average'], $r['highest'], $r['lowest'], $r['pass_rate'],
            ]));
        }

        return view('school-admin.reports.academic', [
            'rows' => $rows, 'exams' => $exams, 'selectedExamId' => $selectedExamId,
        ]);
    }

    protected function csv(string $filename, array $headers, \Illuminate\Support\Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
