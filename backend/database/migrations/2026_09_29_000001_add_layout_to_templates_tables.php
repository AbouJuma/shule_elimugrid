<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-built designs to choose from (spec: "should be like this on templates
 * we should configure pre templates and be able to choose which ID card /
 * certificate is active"), instead of only colour/orientation knobs on one
 * fixed layout. `layout` picks which Blade design renders the PDF;
 * config/template_layouts.php is the catalog. Existing rows get 'classic' —
 * the layout that already existed — so nothing already issued changes look.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->string('layout', 40)->default('classic')->after('name');
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('layout', 40)->default('classic')->after('name');
        });

        DB::table('id_card_templates')->update(['layout' => 'classic']);
        DB::table('certificate_templates')->update(['layout' => 'classic']);
    }

    public function down(): void
    {
        Schema::table('id_card_templates', fn (Blueprint $t) => $t->dropColumn('layout'));
        Schema::table('certificate_templates', fn (Blueprint $t) => $t->dropColumn('layout'));
    }
};
