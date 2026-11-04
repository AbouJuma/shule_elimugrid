<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A teacher-authored multiple-choice quiz for one class. 'mode'
        // decides what an attempt means: 'graded' is a single official
        // attempt whose score is kept on the student's record (a real
        // exam); 'practice' allows unlimited retakes purely for
        // self-review, never a permanent result. Only 'published' exams
        // are visible to students/parents — while 'draft', a teacher can
        // still add/reorder/fix questions without students seeing a
        // half-built quiz.
        Schema::create('online_exams', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('mode', ['graded', 'practice'])->default('graded');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'school_class_id']);
        });

        Schema::create('online_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_exam_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Exactly one choice per question has is_correct = true (enforced
        // in the request/service, not the schema — matches how
        // exam_subjects/attendance's "exactly one of X" rules are handled
        // elsewhere in this app: validated in PHP, not a DB constraint).
        Schema::create('online_exam_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_exam_question_id')->constrained()->cascadeOnDelete();
            $table->string('choice_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One row per time a student opens the exam. For 'graded' exams,
        // OnlineExamService refuses a second attempt once one is
        // submitted — the same "first-and-locked" shape as an attendance
        // register, for the same reason (a result should mean something).
        Schema::create('online_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('max_score')->nullable();
            $table->timestamps();

            $table->index(['online_exam_id', 'student_id']);
        });

        Schema::create('online_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_exam_question_id')->constrained()->cascadeOnDelete();
            // Nullable: a question left unanswered at submit time still
            // gets a row (0 points), rather than silently vanishing from
            // the per-question breakdown a student sees afterward.
            $table->foreignId('online_exam_choice_id')->nullable()->constrained('online_exam_choices')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['online_exam_attempt_id', 'online_exam_question_id'], 'online_exam_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_answers');
        Schema::dropIfExists('online_exam_attempts');
        Schema::dropIfExists('online_exam_choices');
        Schema::dropIfExists('online_exam_questions');
        Schema::dropIfExists('online_exams');
    }
};
