<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedInteger('next_certificate_sequence')->default(1)->after('next_receipt_sequence');
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_template_id')->nullable()->constrained()->nullOnDelete();

            // Unique per school (like invoice numbers).
            $table->string('certificate_number');

            // Globally unique and cryptographically random — this, not the
            // certificate_number, is what the QR code encodes. Never
            // derived from the numeric id, so it can't be enumerated.
            $table->string('verification_token', 64)->unique();

            $table->string('title'); // e.g. "Certificate of Completion"
            $table->text('achievement')->nullable(); // free-text body, e.g. "for outstanding performance in..."
            $table->date('issued_date');
            $table->enum('status', ['valid', 'revoked'])->default('valid');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'certificate_number']);
            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('next_certificate_sequence');
        });
    }
};
