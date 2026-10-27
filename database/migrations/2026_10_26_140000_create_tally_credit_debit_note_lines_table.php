<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit Note / Debit Note voucher lines from Tally's Day Book -- previously
 * discarded entirely by hrx:tally-push (see the `continue` this table's
 * sync replaces in TallyPushCommand's mapping loop, right after the
 * Sales Order/Sales Bill check). Confirmed live against the real Tally
 * gateway: these vouchers already arrive in the Day Book payload with full
 * item/HSN/GST detail, they were just never mapped or stored.
 *
 * Flat per-line shape, same pattern as tally_sale_lines/tally_purchase_lines
 * (header fields repeated on every line) rather than a separate header
 * table -- there is no other consumer yet needing a normalized header, and
 * adding one speculatively would be unused weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_credit_debit_note_lines', function (Blueprint $t) {
            $t->id();
            $t->string('company', 20)->default('hitech')->index();
            // Tally's own voucher GUID -- stable across re-pushes of the same voucher, used as the replace-by-key
            // scope (same role invoice_no plays for tally_sale_lines).
            $t->string('note_key', 80)->index();
            // credit_sale | debit_sale | credit_purchase | debit_purchase -- derived from the voucher type name
            // (e.g. "Credit Note (Sale)-PB", "Debit Note (Pur)-RMG") since Tally has no single flag for this.
            $t->string('note_type', 20)->index();
            $t->string('note_no', 60)->nullable();
            $t->string('series', 20)->nullable();
            $t->date('note_date')->index();
            $t->string('party_name')->nullable();
            $t->string('normalized_name')->nullable()->index();
            $t->string('item_name', 500)->nullable();
            $t->string('product_group', 60)->nullable();
            $t->string('hsn', 16)->nullable();
            $t->decimal('gst_rate', 5, 2)->nullable();
            $t->decimal('quantity', 15, 3)->nullable();
            $t->string('unit', 20)->nullable();
            $t->decimal('rate', 15, 4)->nullable();
            $t->decimal('amount', 15, 2);
            $t->string('reference', 120)->nullable();
            $t->string('order_no', 60)->nullable();
            // No timestamps columns -- matches tally_sale_lines/tally_purchase_lines, whose rows are plain
            // DB::table()->insert() from TallyPushController::replaceByKey(), not through an Eloquent model.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_credit_debit_note_lines');
    }
};
