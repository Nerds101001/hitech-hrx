<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manually-entered tracking info, kept SEPARATE from tally_deliveries on purpose: that table is a
        // full delete-and-replace sync target (every Tally push wipes and rebuilds it), so anything written
        // there by a person would be silently destroyed on the next 2-hourly sync. This table is never
        // touched by the sync pipeline -- only by a person, from the Work Orders page -- so it survives.
        Schema::create('work_order_tracking_notes', function (Blueprint $table) {
            $table->id();
            $table->string('company', 20);
            $table->string('order_no', 60);
            $table->string('carrier')->nullable();
            $table->string('tracking_ref', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company', 'order_no'], 'wotn_company_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_tracking_notes');
    }
};
