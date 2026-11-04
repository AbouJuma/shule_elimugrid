<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedInteger('next_invoice_sequence')->default(1)->after('next_admission_sequence');
            $table->unsignedInteger('next_control_number_sequence')->default(1)->after('next_invoice_sequence');
            $table->unsignedInteger('next_receipt_sequence')->default(1)->after('next_control_number_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['next_invoice_sequence', 'next_control_number_sequence', 'next_receipt_sequence']);
        });
    }
};
