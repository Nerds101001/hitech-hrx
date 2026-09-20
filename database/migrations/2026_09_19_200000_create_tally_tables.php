<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tally's daily text exports, loaded as-is so they can be browsed and
 * compared against the manual Sales Report and Setu. Every table is a full
 * snapshot that `hrx:import-tally` replaces on each run (the exports are
 * complete for their date window, not deltas), so none of these hold data
 * anyone edits in HRX. `party_code` is Tally's own 6-digit customer code --
 * NOT the CRM company code (those are a different, 4-digit numbering).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_imports', function (Blueprint $t) {
            $t->id();
            $t->string('status', 20)->default('running'); // running | ok | partial | failed
            $t->string('source_dir', 500)->nullable();
            $t->json('summary')->nullable();              // per-file counts, date ranges, warnings
            $t->text('error')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        // Abc.txt -- one row per sales invoice (invoice value INCLUDING GST,
        // which is also what Setu's `tptl` turned out to be).
        Schema::create('tally_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no', 60)->unique();
            $t->date('invoice_date')->index();
            $t->string('party_code', 20)->nullable()->index();
            $t->string('party_name')->nullable();
            $t->string('normalized_name')->nullable()->index();
            $t->string('salesperson', 120)->nullable()->index();
            $t->decimal('amount', 15, 2)->default(0);
            $t->string('lr_no', 60)->nullable();
            $t->string('transporter')->nullable();
            $t->string('transporter_phone', 60)->nullable();
            $t->string('destination', 120)->nullable();
            $t->string('po_number', 120)->nullable();
            // Stock moving between our own branches -- not a customer sale.
            $t->boolean('is_branch_transfer')->default(false)->index();
            // Columns whose meaning the export doesn't make clear (kept, not guessed at).
            $t->json('extra')->nullable();
            $t->timestamps();
        });

        // Invoice Details.txt -- the item lines of those invoices.
        Schema::create('tally_invoice_lines', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no', 60)->index();
            $t->date('invoice_date')->nullable();
            $t->string('item_name', 500)->nullable();
            $t->decimal('quantity', 15, 3)->nullable();
            $t->string('unit', 20)->nullable();
        });

        // OutstandingDet.txt -- open bills. Amount sign follows Tally: a
        // "(-)" amount is the opposite side of the ledger's normal balance.
        Schema::create('tally_outstanding', function (Blueprint $t) {
            $t->id();
            $t->string('bill_ref', 60)->nullable()->index();
            $t->date('bill_date')->nullable()->index();
            $t->string('party_code', 20)->nullable()->index();
            $t->string('party_name')->nullable();
            $t->string('normalized_name')->nullable()->index();
            $t->string('ledger_group', 120)->nullable()->index();
            $t->decimal('amount', 15, 2)->default(0);
            $t->unsignedSmallInteger('credit_days')->nullable();
        });

        // PartyWiseSaleOrder.txt -- open sale orders (one row per order line).
        Schema::create('tally_sale_orders', function (Blueprint $t) {
            $t->id();
            $t->date('order_date')->nullable()->index();
            $t->string('order_no', 60)->nullable()->index();
            $t->string('status', 40)->nullable();
            $t->string('party_code', 20)->nullable()->index();
            $t->string('party_name')->nullable();
            $t->string('normalized_name')->nullable()->index();
            $t->decimal('quantity', 15, 3)->nullable();
            $t->string('unit', 20)->nullable();
            $t->decimal('amount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        foreach (['tally_sale_orders', 'tally_outstanding', 'tally_invoice_lines', 'tally_invoices', 'tally_imports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
