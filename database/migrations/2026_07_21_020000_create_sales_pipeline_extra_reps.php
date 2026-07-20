<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional representatives on a customer: some customers are worked by more
 * than one salesperson / CCare / New Biz person. Anyone tagged here sees the
 * customer in their login alongside the primary representative.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_pipeline_extra_reps')) {
            Schema::create('sales_pipeline_extra_reps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_pipeline_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['sales_pipeline_id', 'user_id'], 'spx_unique');
                $table->index('user_id', 'spx_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_pipeline_extra_reps');
    }
};
