<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedInteger('next_id_card_sequence')->default(1)->after('next_certificate_sequence');
        });

        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('id_card_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('card_number');
            // Globally unique, cryptographically random — same rule as
            // certificate verification_token: never derived from the id.
            $table->string('verification_token', 64)->unique();

            // A student normally has exactly one ACTIVE card; issuing a
            // replacement (lost/damaged card) supersedes the previous one
            // rather than deleting it, preserving history.
            $table->enum('status', ['active', 'revoked', 'superseded'])->default('active');
            $table->date('issued_date');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'card_number']);
            $table->index(['school_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_cards');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('next_id_card_sequence');
        });
    }
};
