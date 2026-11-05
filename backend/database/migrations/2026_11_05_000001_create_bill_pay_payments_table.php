<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AzamPay's Bill Pay product (separate from the Checkout API the parent
 * app uses) — a guardian pays by entering the student's control number
 * directly into *any* bank's or mobile network's own "pay bill" menu, no
 * app required. AzamPay calls our name-lookup/payment/status-check
 * endpoints as the "biller". One incoming lump sum here can cover more
 * than one of the student's open invoices (control numbers aren't
 * per-invoice — see the 2026_09_30 migration), so this row is the lump
 * sum as AzamPay sees it, and the FIFO split across invoices lives as
 * ordinary `payments` rows pointing back here via bill_pay_payment_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_pay_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('bill_identifier', 20);
            // AzamPay/the bank's own reference for this payment attempt —
            // the idempotency key, since a retried callback must not apply
            // the same money twice.
            $table->string('fsp_reference_id')->unique();
            $table->string('pg_reference_id')->nullable();
            // Our own reference, generated here and handed back to AzamPay
            // so their later status-check calls can find this row again.
            $table->uuid('merchant_reference_id')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('fsp_code', 40)->nullable();
            $table->string('bill_type', 40)->nullable();
            $table->enum('status', ['completed', 'failed'])->default('completed');
            $table->string('message')->nullable();
            $table->json('raw_request')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bill_pay_payment_id')->nullable()->after('recorded_by')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_pay_payment_id');
        });

        Schema::dropIfExists('bill_pay_payments');
    }
};
