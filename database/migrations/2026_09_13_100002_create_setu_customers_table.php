<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A local cache of Setu's (Tally's) customer list. Setu has no per-customer
 * lookup endpoint — the only way to get its data is one large unfiltered
 * pull (tens of MB, several seconds), which is far too slow to hit live from
 * an interactive search/verify UI. This table is that pull's result, kept
 * fresh by `hrx:sync-setu-customers`, so the mapping screen reads instantly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setu_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Setu/Tally's own spelling — never rewritten
            $table->string('normalized_name');
            // {"2026-07": 12.5, "2026-08": 9.1, ...} — Lakhs, most recent few
            // calendar months only. A display cache, not a ledger of record.
            $table->json('recent_months')->nullable();
            $table->decimal('recent_total', 12, 3)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('normalized_name');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setu_customers');
    }
};
