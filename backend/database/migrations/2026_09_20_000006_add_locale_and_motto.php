<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('status');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('motto')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn('motto'));
    }
};
