<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The FULL ledger master (every account, not just customers the way tally_parties is scoped), a full-table-replace
 * snapshot fed by hrx:tally-push-ledgers from Tally's Ledger collection. One row per ledger: its immediate group,
 * resolved primary group, closing balance, and the identity fields (GSTIN/PAN/contact).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_ledgers', function (Blueprint $t) {
            $t->id();
            $t->string('name')->index();
            $t->string('normalized_name')->nullable()->index();
            $t->string('ledger_group', 120)->nullable()->index();     // immediate parent group
            $t->string('primary_group', 120)->nullable()->index();    // resolved top-of-chain bucket (Sundry Debtors, ...)
            $t->decimal('closing_balance', 16, 2)->nullable();        // sign as a person reads it (owed-to-us positive)
            $t->decimal('credit_limit', 16, 2)->nullable();
            $t->string('gstin', 20)->nullable();
            $t->string('pan', 20)->nullable();
            $t->string('contact_person')->nullable();
            $t->string('contact_mobile')->nullable();
            $t->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_ledgers');
    }
};
