<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-school grade boundaries. A row means "a percentage >= min_percent
        // earns this grade" — the highest matching min_percent wins.
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 5);
            $table->unsignedTinyInteger('min_percent');
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'grade']);
            $table->unique(['school_id', 'min_percent']);
        });

        Schema::create('report_card_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->text('class_teacher_remark')->nullable();
            $table->text('head_teacher_remark')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_remarks');
        Schema::dropIfExists('grade_scales');
    }
};
