<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape')->after('name');
            $table->enum('photo_position', ['left', 'right', 'center'])->default('left')->after('orientation');
            $table->string('primary_color', 7)->default('#0a1f44')->after('photo_position');
            $table->string('secondary_color', 7)->default('#d4a017')->after('primary_color');
            $table->string('custom_text')->nullable()->after('secondary_color');
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape')->after('type');
            $table->string('primary_color', 7)->default('#0a1f44')->after('orientation');
            $table->string('secondary_color', 7)->default('#d4a017')->after('primary_color');
            $table->string('custom_text')->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->dropColumn(['orientation', 'photo_position', 'primary_color', 'secondary_color', 'custom_text']);
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['orientation', 'primary_color', 'secondary_color', 'custom_text']);
        });
    }
};
