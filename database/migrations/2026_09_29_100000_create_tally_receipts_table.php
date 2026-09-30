<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt vouchers from Tally's API (hrx:tally-push) -- actual customer payments, as distinct from
 * "Receipt Note" vouchers (goods receipts, not money) which are deliberately excluded. Powers "last
 * payment date" / "collected this period" on the Finance page: real collections, not just the
 * outstanding side. receipt_key is the delete-then-reinsert scope key (date|voucher_type|receipt_no),
 * needed because receipt_no alone repeats across the branch/bank series encoded only in voucher_type
 * (e.g. "Receipt - CH" vs "Receipt-LDH"), never in the number itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_key', 150)->unique();
            $table->date('receipt_date')->index();
            $table->string('receipt_no', 60);
            $table->string('voucher_type', 60)->nullable();
            $table->string('party_name')->nullable();
            $table->string('normalized_name')->nullable()->index();
            $table->string('party_code', 20)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('bank_ledger')->nullable();
            $table->string('reference', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_receipts');
    }
};
