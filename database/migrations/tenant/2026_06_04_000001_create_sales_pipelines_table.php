<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('party_name');
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('ccare_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('total_business_potential', 15, 2)->default(0);
            
            // YoY Fields
            $table->decimal('yoy_22_23', 15, 2)->default(0);
            $table->decimal('yoy_23_24', 15, 2)->default(0);
            $table->decimal('yoy_24_25', 15, 2)->default(0);
            $table->decimal('yoy_25_26', 15, 2)->default(0);

            // Status/Stage (e.g. Quotation, Prospect, PO Awaited)
            $table->string('status_stage')->nullable();
            
            // Additional info
            $table->string('address')->nullable();
            $table->string('product')->nullable();

            $table->string('tenant_id', 191)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_pipelines');
    }
};
