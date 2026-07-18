<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesperson_annual_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('fy_22_23', 12, 2)->default(0);
            $table->decimal('fy_23_24', 12, 2)->default(0);
            $table->decimal('fy_24_25', 12, 2)->default(0);
            $table->decimal('fy_25_26', 12, 2)->default(0);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesperson_annual_sales');
    }
};
