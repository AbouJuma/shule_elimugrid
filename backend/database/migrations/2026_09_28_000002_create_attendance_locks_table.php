<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A register (a class's whole-day one, or one of its subject-scoped
        // ones — mirrors attendance_records' own subject_id nullability)
        // locks itself the moment it's first saved for a date. One row per
        // register, independent of how many students are in it, so an
        // empty-looking register can still be tracked as locked/unlocked
        // and unlocking never needs to touch the underlying records.
        Schema::create('attendance_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');
            $table->timestamps();

            $table->unique(['school_class_id', 'subject_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_locks');
    }
};
