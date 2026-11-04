<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each class needs for the automatic timetable: which teacher
        // takes a subject and how many lessons a week it gets.
        Schema::create('timetable_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('periods_per_week')->default(0);
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id'], 'tt_req_class_subject_unique');
        });

        // Lessons placed by the generator, so "regenerate" can replace exactly
        // those and never a lesson someone placed by hand.
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->boolean('generated')->default(false)->after('room');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', fn (Blueprint $table) => $table->dropColumn('generated'));
        Schema::dropIfExists('timetable_requirements');
    }
};
