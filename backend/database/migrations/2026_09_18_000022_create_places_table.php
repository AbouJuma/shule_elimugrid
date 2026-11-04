<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately NOT tenant-scoped (no school_id, no BelongsToTenant):
        // a real-world place is the same place regardless of which school
        // looked it up. This is a shared, growing reference dataset, not
        // per-tenant business data — every school's searches make every
        // other school's searches faster.
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('source')->default('local'); // local / nominatim
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
