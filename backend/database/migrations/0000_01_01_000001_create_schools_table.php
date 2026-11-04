<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            // External-facing identifier (QR codes, verification links, API refs) so
            // sequential internal IDs are never exposed outside the platform.
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('registration_number')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('timezone')->default('Africa/Dar_es_Salaam');
            $table->string('currency', 3)->default('TZS');
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->foreignId('subscription_package_id')->nullable()
                ->constrained('subscription_packages')->nullOnDelete();
            // Per-school branding/config overrides that don't warrant their own column.
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
