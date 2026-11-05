<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per mobile-money checkout attempt (AzamPay today, any gateway
 * later) — the audit trail / "proof of payment" for a gateway payment,
 * independent of whether it ever turns into a Payment row. Kept even for
 * failed or abandoned attempts so a disputed "I paid but it's not showing"
 * claim can be checked against what the gateway actually said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider', 40);
            $table->string('gateway', 40)->default('azampay');
            // Ours — generated before calling the gateway, and the only key
            // we trust to match an inbound callback back to an invoice.
            // A control number alone can't do this: it's the student's,
            // reused across every invoice they have (see control_number
            // on invoices), so several open invoices can share one.
            $table->uuid('external_id')->unique();
            $table->string('msisdn', 20);
            $table->string('control_number', 20);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('TZS');

            $table->enum('status', ['initiated', 'pending', 'completed', 'failed'])->default('initiated');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('failure_reason')->nullable();

            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('raw_callback')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_transactions');
    }
};
