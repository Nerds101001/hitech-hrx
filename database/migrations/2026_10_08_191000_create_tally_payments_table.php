<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment vouchers -- money paid OUT (the mirror of tally_receipts). Fed by the same Day Book push. Keyed by
 * payment_key (date|type|number, with a GUID-tail suffix if two share it), the same collision-safe scheme receipts
 * use, so re-pushing a day replaces rather than duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_payments', function (Blueprint $t) {
            $t->id();
            $t->string('payment_key', 120)->index();
            $t->date('payment_date')->nullable()->index();
            $t->string('payment_no', 60)->nullable();
            $t->string('voucher_type', 80)->nullable();
            $t->string('party_name')->nullable();            // who was paid (supplier / expense head)
            $t->string('normalized_name')->nullable()->index();
            $t->decimal('amount', 15, 2)->default(0);
            $t->string('bank_ledger')->nullable();           // the bank/cash account it came out of
            $t->string('reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_payments');
    }
};
