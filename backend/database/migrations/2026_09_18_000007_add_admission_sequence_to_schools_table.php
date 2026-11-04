<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Monotonically increasing per-school counter for admission
            // number generation (AdmissionNumberGenerator). Incremented
            // under a row lock so concurrent admissions never collide,
            // even though numbers are also never reused after a deletion.
            $table->unsignedInteger('next_admission_sequence')->default(1)->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('next_admission_sequence');
        });
    }
};
