<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_pipeline_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_pipeline_id')->constrained()->cascadeOnDelete();
            
            // Storing as YYYY-MM (e.g. 2026-04)
            $table->string('month_year', 7);
            
            $table->decimal('forecast_amount', 15, 2)->default(0);
            $table->decimal('sale_amount', 15, 2)->default(0);
            $table->decimal('pending_amount', 15, 2)->default(0);
            $table->text('remarks')->nullable();

            $table->string('tenant_id', 191)->nullable();
            $table->timestamps();

            // Ensure unique month per pipeline
            $table->unique(['sales_pipeline_id', 'month_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_pipeline_months');
    }
};
