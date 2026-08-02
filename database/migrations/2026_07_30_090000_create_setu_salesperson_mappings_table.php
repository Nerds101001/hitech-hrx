<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a Tally salesperson ledger name (free text, from the accounting
 * system) to the HRX user it refers to. Tally names don't reliably match
 * HRX employee records by string comparison alone — common surnames collide
 * between two or three people, and the ledger also contains non-person
 * entries (accounting buckets, city/state territories, brand names) that
 * must never resolve to a user. This table is the reviewed, corrected
 * result of that one-time reconciliation, kept in the DB (not a static
 * config array) so it can be looked up and corrected without re-running the
 * fuzzy-match pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setu_salesperson_mappings')) {
            Schema::create('setu_salesperson_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('tally_name')->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status')->default('pending'); // pending | confirmed | excluded
                $table->string('note')->nullable(); // why excluded, or match confidence at confirmation time
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->index('user_id', 'ssm_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setu_salesperson_mappings');
    }
};
