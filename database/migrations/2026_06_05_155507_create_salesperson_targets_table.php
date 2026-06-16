<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salesperson_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('salesperson_id');
            $table->string('month_year', 10);
            $table->decimal('kingo_target', 15, 2)->default(0);
            $table->decimal('bingo_target', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('salesperson_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['salesperson_id', 'month_year'], 'salesperson_month_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesperson_targets');
    }
};
