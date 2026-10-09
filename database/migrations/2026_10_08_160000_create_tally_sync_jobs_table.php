<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill / re-sync requests a viewer creates from the Tally Push admin page. The live server cannot pull from
 * Tally (LAN-only), so each row is a job the OFFICE PC claims (over the signed API) and runs with hrx:tally-push,
 * writing its progress back here so the page can show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_sync_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id')->nullable()->index();
            $t->date('from_date');
            $t->date('to_date');
            // pending -> claimed -> running -> done | failed
            $t->string('status', 16)->default('pending')->index();
            $t->string('label', 64)->nullable();                 // e.g. "May 2026" for display
            $t->unsignedBigInteger('requested_by_id')->nullable();
            $t->string('requested_by_name')->nullable();
            $t->timestamp('requested_at')->useCurrent();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->string('claimed_ip', 45)->nullable();
            $t->unsignedInteger('days_total')->default(0);
            $t->unsignedInteger('days_done')->default(0);
            $t->unsignedInteger('invoices')->default(0);
            $t->unsignedInteger('invoice_lines')->default(0);
            $t->unsignedInteger('sale_lines')->default(0);
            $t->unsignedInteger('sale_orders')->default(0);
            $t->unsignedInteger('deliveries')->default(0);
            $t->unsignedInteger('receipts')->default(0);
            $t->text('message')->nullable();
            $t->timestamps();

            $t->foreign('requested_by_id')->references('id')->on('users')->nullOnDelete();
            $t->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_sync_jobs');
    }
};
