<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One exam can now be run across several classes at once (e.g. a
        // shared "Mid-Term" for both Form 1 and Form 2) — replaces the old
        // single school_class_id on `exams`. Marks stay keyed by
        // exam+subject+student regardless (a student's own class), so no
        // change is needed there; only which classes an exam belongs to,
        // and which one to show students for when entering marks.
        Schema::create('exam_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'school_class_id']);
        });

        // Backfill: every existing exam already has exactly one class.
        foreach (DB::table('exams')->select('id', 'school_class_id')->get() as $exam) {
            DB::table('exam_classes')->insert([
                'exam_id' => $exam->id,
                'school_class_id' => $exam->school_class_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('exams', function (Blueprint $table) {
            // Temporary: keeps school_class_id covered by *some* index
            // while the old composite unique (its only index right now) is
            // dropped next — MySQL refuses to drop an index that's the
            // last one covering a foreign-keyed column ("Cannot drop
            // index ...: needed in a foreign key constraint"), same as the
            // attendance-by-subject migration hit.
            $table->index('school_class_id', 'exams_school_class_id_tmp_index');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique(['school_class_id', 'academic_year_id', 'name']);
        });

        // Dropping the temp index needs a different moment on each driver:
        // - MySQL runs one ALTER TABLE statement per command, in order, so
        //   an explicit dropIndex() here — before the FK/column are gone —
        //   hits the exact same "needed in a foreign key constraint" error
        //   this index exists to avoid. Left alone, MySQL auto-drops a
        //   single-column index the moment its column is dropped, so
        //   dropConstrainedForeignId() below is enough on its own.
        // - SQLite instead recreates the whole table for a column drop,
        //   from a snapshot of indexes taken *before* this migration's
        //   commands ran — so if the temp index isn't already gone by the
        //   time that happens, it tries to recreate an index over a column
        //   that no longer exists and errors ("no such column").
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropIndex('exams_school_class_id_tmp_index');
            });
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_class_id');
            $table->unique(['school_id', 'academic_year_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'academic_year_id', 'name']);
            $table->foreignId('school_class_id')->nullable()->after('term_id')->constrained()->nullOnDelete();
        });

        // Best-effort: an exam that had gained a second class while this
        // migration was applied only gets one of them back.
        foreach (DB::table('exam_classes')->select('exam_id', DB::raw('MIN(school_class_id) as school_class_id'))->groupBy('exam_id')->get() as $row) {
            DB::table('exams')->where('id', $row->exam_id)->update(['school_class_id' => $row->school_class_id]);
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->unique(['school_class_id', 'academic_year_id', 'name']);
        });

        Schema::dropIfExists('exam_classes');
    }
};
