<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A control number is now the student's own, permanent, reusable payment
 * reference — assigned once and never changed, the way an admission number
 * is — rather than a fresh one generated per invoice. Every invoice for a
 * student carries the same number, matching how control numbers are actually
 * used for reconciliation (spec/user feedback: "usable, not changing for
 * student — like its primary key"). Numeric, starting with 8 (no "CN-"
 * prefix), so it looks like the control numbers schools already recognise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('control_number', 20)->nullable()->after('admission_number');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedInteger('next_student_control_sequence')->default(1)->after('next_control_number_sequence');
        });

        // An invoice's control_number no longer belongs to that one invoice —
        // it's copied from the student and repeats across every invoice they
        // have, so the old per-invoice uniqueness no longer holds.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'control_number']);
            $table->index(['school_id', 'control_number']);
        });

        // Backfill: give every existing student missing one their own control
        // number, then bring their existing invoices (if any) into line with it
        // — so a school upgrading to this doesn't end up with two different
        // "control numbers" floating around for the same student.
        DB::table('students')->whereNull('control_number')->orderBy('id')->select('id', 'school_id')
            ->get()->groupBy('school_id')->each(function ($students, $schoolId) {
                $school = DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
                $sequence = $school->next_student_control_sequence;

                foreach ($students as $student) {
                    $controlNumber = sprintf('8%09d', $sequence++);
                    DB::table('students')->where('id', $student->id)->update(['control_number' => $controlNumber]);
                    DB::table('invoices')->where('student_id', $student->id)->update(['control_number' => $controlNumber]);
                }

                DB::table('schools')->where('id', $schoolId)->update(['next_student_control_sequence' => $sequence]);
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'control_number']);
            $table->unique(['school_id', 'control_number']);
        });

        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn('next_student_control_sequence'));
        Schema::table('students', fn (Blueprint $t) => $t->dropColumn('control_number'));
    }
};
