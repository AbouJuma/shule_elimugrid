<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('transport_route_id')->nullable()->after('stream_id')
                ->constrained('transport_routes')->nullOnDelete();
            $table->foreignId('route_stop_id')->nullable()->after('transport_route_id')
                ->constrained('route_stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_stop_id');
            $table->dropConstrainedForeignId('transport_route_id');
        });
    }
};
