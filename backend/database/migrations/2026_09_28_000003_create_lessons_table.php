<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A recorded/linked video lesson a teacher posts for one class (and
        // optionally one subject) to learn from and review any time —
        // deliberately one class per lesson, unlike the exams' multi-class
        // pivot: content like this is authored per-class from the start
        // (a teacher picks who they're teaching, not a shared timetable
        // slot), so there's no real "one lesson, several classes" case to
        // support yet.
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Exactly one of these is set (enforced in StoreLessonRequest):
            // a pasted external link, or a file uploaded to our own storage.
            $table->string('video_url')->nullable();
            $table->string('video_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
