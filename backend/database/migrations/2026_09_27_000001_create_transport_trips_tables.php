<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One run of a bus along a route: started by the driver, ended by the driver.
        Schema::create('transport_trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            // morning = pick students up (stops in sequence order, ending at school);
            // afternoon = drop them home (stops in reverse sequence order).
            $table->string('direction', 10)->default('morning');
            $table->string('status', 15)->default('in_progress'); // in_progress | completed
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['vehicle_id', 'status']);
        });

        // When the bus reached each stop (automatically near the stop, or by the driver's tap).
        Schema::create('trip_stop_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_trip_id')->constrained('transport_trips')->cascadeOnDelete();
            $table->foreignId('route_stop_id')->constrained('route_stops')->cascadeOnDelete();
            $table->timestamp('arrived_at');
            $table->string('source', 10)->default('auto'); // auto | manual
            $table->timestamps();

            $table->unique(['transport_trip_id', 'route_stop_id']);
        });

        // The breadcrumb trail: every position the app reported during the trip.
        Schema::create('trip_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_trip_id')->constrained('transport_trips')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 6, 1)->nullable();
            $table->timestamp('recorded_at');

            $table->index(['transport_trip_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
        Schema::dropIfExists('trip_stop_visits');
        Schema::dropIfExists('transport_trips');
    }
};
