<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->decimal('basic_salary', 12, 2)->default(0)->after('employment_type');
            $table->string('bank_name')->nullable()->after('basic_salary');
            $table->string('bank_account_number')->nullable()->after('bank_name');
        });

        // Reusable pay elements: an allowance adds to gross pay, a
        // deduction comes off it. `fixed` uses the amount as-is, `percent`
        // takes that % of basic salary — so statutory-style deductions
        // (pension, tax) stay configurable rather than hardcoded to one
        // country's rules.
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction']);
            $table->enum('calculation', ['fixed', 'percent'])->default('fixed');
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->boolean('apply_to_all')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'name']);
        });

        // Per-employee override/addition of a component.
        Schema::create('staff_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['staff_profile_id', 'payroll_component_id'], 'staff_component_unique');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'period']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('total_allowances', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'staff_profile_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction']);
            $table->decimal('amount', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('staff_salary_components');
        Schema::dropIfExists('payroll_components');
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['basic_salary', 'bank_name', 'bank_account_number']);
        });
    }
};
