<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A parent login can be created with only a phone number (no email), so
 * email can no longer be a required, always-present identifier.
 *
 * phone is NOT made DB-unique here, on purpose: for staff it's just
 * contact info that doubles as their first password's seed (see
 * StoreStaffRequest), not a login key, and two staff members sharing one
 * really can coincidentally share a number — StaffManagementTest relies
 * on exactly that. Phone only needs to be unique at the moment it's
 * chosen as a parent's actual sign-in identifier, which is enforced where
 * that login is created (GuardianAccountService / its controllers), not
 * as a blanket constraint on every row in this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
