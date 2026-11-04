<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors attendance_records/attendance_locks, but for staff —
        // one row per staff member per date (no class/subject dimension;
        // a staff register is simply "everyone employed today"), with the
        // same "locks itself once saved" integrity rule as student
        // attendance (Phase 34).
        Schema::create('staff_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->text('remarks')->nullable();
            // How this row was created — a scan (gate check-in) is a
            // separate, always-allowed path from the bulk register save
            // (see StaffAttendanceService); this just records which.
            $table->enum('source', ['register', 'scan'])->default('register');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['school_id', 'date']);
        });

        Schema::create('staff_attendance_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');
            $table->timestamps();

            $table->unique(['school_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance_locks');
        Schema::dropIfExists('staff_attendance_records');
    }
};
