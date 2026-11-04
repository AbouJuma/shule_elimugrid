<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            // What the percentage is taken of: basic salary (the original
            // behaviour), gross pay, or taxable income (gross minus the
            // pre-tax deductions such as pension).
            $table->string('base', 10)->default('basic')->after('calculation');
            // Progressive tax bands: [{"upto": 270000, "rate": 0}, ..., {"upto": null, "rate": 30}].
            // When set, it replaces the fixed/percent calculation.
            $table->json('bands')->nullable()->after('default_amount');
            // A deduction that reduces taxable income (e.g. pension) — computed before tax.
            $table->boolean('pre_tax')->default(false)->after('apply_to_all');
            // Cost to the school on top of the salary (employer pension, WCF, SDL):
            // shown on the payslip, never taken from the employee's pay.
            $table->boolean('employer_paid')->default(false)->after('pre_tax');
        });

        Schema::table('payslip_lines', function (Blueprint $table) {
            $table->boolean('is_employer')->default(false)->after('amount');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('employer_cost', 12, 2)->default(0)->after('net_pay');
        });

        // One-off amounts for a single month's payroll: arrears, back-pay,
        // bonuses, salary advances to recover, corrections.
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('name');
            $table->string('type', 10); // allowance | deduction
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'period']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('is_paid')->default(true)->after('days_per_year');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn('is_paid'));
        Schema::dropIfExists('payroll_adjustments');
        Schema::table('payslips', fn (Blueprint $table) => $table->dropColumn('employer_cost'));
        Schema::table('payslip_lines', fn (Blueprint $table) => $table->dropColumn('is_employer'));
        Schema::table('payroll_components', fn (Blueprint $table) => $table->dropColumn(['base', 'bands', 'pre_tax', 'employer_paid']));
    }
};
