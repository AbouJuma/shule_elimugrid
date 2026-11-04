<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();

            // Unique per school, not globally.
            $table->string('invoice_number');
            $table->string('control_number');

            $table->decimal('total_amount', 12, 2);
            // Running total of successful payments — updated exclusively by
            // PaymentService inside a transaction alongside the payment
            // insert; never edited directly. Balance is total - paid,
            // computed on read, never stored (so it can't drift).
            $table->decimal('amount_paid', 12, 2)->default(0);

            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->date('issued_date');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'invoice_number']);
            $table->unique(['school_id', 'control_number']);
            $table->index(['school_id', 'status']);
        });

        // Line items copied from the fee structure AT GENERATION TIME —
        // deliberately decoupled from fee_structure_items so editing a fee
        // structure later never silently rewrites an already-issued
        // invoice (spec section 57: financial records aren't silently
        // modified).
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number');
            $table->decimal('amount', 12, 2);
            // Reversals are their own negative-amount payment rows, never
            // edits/deletes of an original payment — preserves history.
            $table->enum('type', ['payment', 'reversal'])->default('payment');
            $table->enum('method', ['cash', 'bank_transfer', 'mobile_money', 'other'])->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('paid_on');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_number']);
            $table->index(['school_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
