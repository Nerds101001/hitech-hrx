<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces setu_salesperson_mappings' role going forward: maps Tally's own
 * `salesperson` tag (tally_invoices.salesperson / tally_sale_lines.salesperson
 * — a per-voucher Cost Centre value Tally itself assigns, e.g. "Ajinkya
 * Pathare") to the HRX user it refers to, same reviewed tri-state shape
 * (pending/confirmed/excluded) as the Setu table it supersedes.
 *
 * Originally built against tally_ledgers.ledger_group, which turned out to
 * be a noisy proxy (171 values, many unrelated accounting buckets coincided
 * with real names) rather than the real signal — cross-checked against
 * Setu's own canonical salesperson list, tally_invoices.salesperson matches
 * almost exactly (72 clean values, all but 6 being real salespeople).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_group_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tally_salesperson')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('pending'); // pending | confirmed | excluded
            $table->string('note')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->index('user_id', 'sgm_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_group_mappings');
    }
};
