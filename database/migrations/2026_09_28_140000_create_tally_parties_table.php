<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small ledger-master sync, separate from the transactional (voucher-based) push: credit limit, GSTIN, PAN
 * and a contact person/mobile per customer, pulled straight from Tally's ledger master (TallyGateway::
 * ledgerMaster()) rather than derived from invoices. Keyed by normalized_name (the same matching key used
 * everywhere else in this app) since Tally's own party_code isn't retrievable via the API -- see
 * project_tally_push_automation.md. Full-table replace on each sync: this is a small, complete master list
 * every time (~a few thousand ledgers, ~4-5s to pull), not an incremental feed like the voucher tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_parties', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_name')->unique();
            $table->string('party_name')->nullable();
            $table->string('ledger_group')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_mobile')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_parties');
    }
};
