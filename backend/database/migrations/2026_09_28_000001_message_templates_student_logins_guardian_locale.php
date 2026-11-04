<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A student can have their own login (Student role) to see their results and timetable.
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
        });

        // Which language a guardian wants messages in (null = the school's default).
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('address');
        });

        // System messages (admission fees, invoice notice, account login) are ordinary
        // templates the school can edit: one row per event and language.
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('event', 40)->nullable()->after('category');
            $table->string('locale', 5)->default('en')->after('event');
            $table->index(['school_id', 'event', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'event', 'locale']);
            $table->dropColumn(['event', 'locale']);
        });
        Schema::table('guardians', fn (Blueprint $t) => $t->dropColumn('locale'));
        Schema::table('students', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
        });
    }
};
