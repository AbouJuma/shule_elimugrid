<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A homework/assignment post for one class — title, instructions,
        // a due date, and an optional attachment (worksheet, reading,
        // etc.). Not auto-graded: a parent/student simply sees it (and the
        // parent can relay it), unlike the separate online-exam engine
        // below, which is specifically for auto-graded multiple choice.
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('due_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeworks');
    }
};
