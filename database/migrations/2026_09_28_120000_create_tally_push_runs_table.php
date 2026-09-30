<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per call to POST /api/tally-push/sync -- the audit trail for "is the Tally sync actually running".
 * Deliberately separate from tally_imports (which tracks the older daily-FILE import): the two pipelines run
 * independently and a reader should be able to tell which one produced what's currently in the tally_* tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_push_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running'); // running | ok | partial | rejected | failed
            $table->string('source_ip', 45)->nullable();
            // {invoices: n, invoice_lines: n, sale_lines: n, sale_orders: n, dates: ["2026-09-28", ...]}
            $table->json('counts')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_push_runs');
    }
};
