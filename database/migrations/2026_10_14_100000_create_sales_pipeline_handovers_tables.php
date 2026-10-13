<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leaver handovers: who held a customer's role before a handover, and from
 * which month the replacement holds it.
 *
 * sales_pipelines keeps holding the CURRENT person in each role column. The
 * ledger records the earlier holders, so every report can credit a past month
 * to whoever held the customer in that month. "The holder in month m is the
 * previous holder of the earliest handover after m, else the current one."
 *
 * Nothing here changes any existing row; with no ledger rows every report
 * behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_pipeline_handover_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('leaver_id')->index();
            $table->string('effective_month', 7);                 // YYYY-MM, first month the replacement is credited
            $table->json('replacements');                        // role => replacement user id
            $table->json('summary')->nullable();                  // what was moved, counted
            $table->longText('before_state')->nullable();         // every row as it was, for undo
            $table->string('status', 12)->default('applied');     // applied | reverted
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_pipeline_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('sales_pipeline_id');
            // salesperson_id | ccare_id | new_biz_id | new_biz_2_id | extra (an additional-rep tag)
            $table->string('role', 20);
            $table->unsignedBigInteger('from_user_id');           // held it before effective_month
            $table->unsignedBigInteger('to_user_id')->nullable(); // holds it from effective_month; null = tag simply ends
            $table->string('effective_month', 7);
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['sales_pipeline_id', 'role']);
            $table->index('from_user_id');
            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_pipeline_handovers');
        Schema::dropIfExists('sales_pipeline_handover_batches');
    }
};
