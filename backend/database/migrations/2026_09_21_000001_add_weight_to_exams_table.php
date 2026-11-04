<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Relative weight of an exam within its term — a term score is the
        // weighted average of the exam percentages. Default 1 keeps every
        // existing term a plain average.
        Schema::table('exams', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->default(1)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('weight'));
    }
};
