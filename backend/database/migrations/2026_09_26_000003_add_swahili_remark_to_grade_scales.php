<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A grade's remark is text the school types ("Excellent"), so it cannot be
        // translated automatically. Let the school supply the Swahili wording.
        Schema::table('grade_scales', function (Blueprint $table) {
            $table->string('remark_sw', 50)->nullable()->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('grade_scales', fn (Blueprint $table) => $table->dropColumn('remark_sw'));
    }
};
