<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same reconciliation shape as setu_match_status/setu_matched_name, but
 * pointed at the app's OWN local tally_ledgers/tally_invoices tables
 * (populated by the Tally push jobs) instead of the external Setu API.
 * tally_matched_name stores the ledger's normalized_name, not an FK — the
 * tally_ledgers table is a full-table-replace snapshot on every sync (see
 * 2026_10_08_193000_create_tally_ledgers_table.php), so row ids don't
 * survive a sync and can't be relied on as a join key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('tally_match_status', ['none', 'similar', 'suggested', 'verified'])->default('none')->after('setu_verified_by_id');
            $table->string('tally_matched_name')->nullable()->after('tally_match_status');
            $table->timestamp('tally_verified_at')->nullable()->after('tally_matched_name');
            $table->foreignId('tally_verified_by_id')->nullable()->constrained('users')->nullOnDelete()->after('tally_verified_at');

            $table->index('tally_match_status');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tally_match_status', 'tally_matched_name', 'tally_verified_at', 'tally_verified_by_id']);
        });
    }
};
