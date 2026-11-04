<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_category_id')->nullable()->after('category')
                ->constrained('inventory_categories')->nullOnDelete();
        });

        // Promote every distinct free-text category already in use into a
        // real category row, then link the items to it.
        $existing = DB::table('inventory_items')->whereNotNull('category')->where('category', '!=', '')
            ->select('school_id', 'category')->distinct()->get();

        foreach ($existing as $row) {
            $id = DB::table('inventory_categories')->insertGetId([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'school_id' => $row->school_id,
                'name' => $row->category,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('inventory_items')->where('school_id', $row->school_id)
                ->where('category', $row->category)->update(['inventory_category_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_category_id');
        });
        Schema::dropIfExists('inventory_categories');
    }
};
