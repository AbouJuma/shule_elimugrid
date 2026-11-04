<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Null = whole-day / class register (existing behaviour, unchanged).
            // Set = a per-subject/lesson attendance record for that same
            // student and date — a student can now have one class-level row
            // plus one row per subject taken that day, side by side.
            $table->foreignId('subject_id')->nullable()->after('school_class_id')
                ->constrained()->nullOnDelete();

            // New unique/index added BEFORE the old one is dropped: MySQL
            // needs student_id to stay covered by *some* index the whole
            // time, since the students-table foreign key relies on the old
            // (student_id, date) unique index for that ("Cannot drop index
            // ...: needed in a foreign key constraint" otherwise). The new
            // unique starts with student_id too, so it can take over.
            //
            // Replaces the old (student_id, date) constraint. Note: MySQL and
            // SQLite both treat NULL as distinct in a unique index, so this
            // doesn't itself stop two concurrent submits of the same
            // class-level (subject_id null) register from racing into two
            // rows — same as every other "mark register" form in this app,
            // where AttendanceService::markForClass()'s updateOrCreate()
            // (matching subject_id with IS NULL / = correctly either way) is
            // what actually prevents duplicates in normal single-admin use.
            $table->unique(['student_id', 'date', 'subject_id']);
            // Explicit short name: the default auto-generated one exceeds
            // MySQL's 64-character identifier limit for this column set.
            $table->index(['school_id', 'school_class_id', 'subject_id', 'date'], 'attendance_records_class_subject_date_index');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unique(['student_id', 'date']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'date', 'subject_id']);
            $table->dropIndex('attendance_records_class_subject_date_index');
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
