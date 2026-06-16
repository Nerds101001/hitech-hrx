<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingo_bingo_scores', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('salesperson_id');
            $table->char('month_year', 7);
            $table->decimal('razor_blade_target', 8, 2)->default(3);
            $table->decimal('razor_blade_achieved', 8, 2)->nullable();
            $table->decimal('new_customers_target', 8, 2)->default(4);
            $table->decimal('new_customers_achieved', 8, 2)->nullable();
            $table->decimal('upsell_target', 8, 2)->default(2);
            $table->decimal('upsell_achieved', 8, 2)->nullable();
            $table->integer('total_customers')->nullable();
            $table->integer('total_orders')->nullable();
            $table->decimal('payment_target', 10, 2)->nullable();
            $table->decimal('payment_achieved', 10, 2)->nullable();
            $table->decimal('rate_target', 8, 2)->default(2);
            $table->decimal('rate_achieved', 8, 2)->nullable();
            $table->decimal('nif_target', 8, 2)->default(3);
            $table->decimal('nif_achieved', 8, 2)->nullable();
            $table->decimal('training_target', 8, 2)->default(2);
            $table->decimal('training_achieved', 8, 2)->nullable();
            $table->decimal('mom_target', 8, 2)->default(3);
            $table->decimal('mom_achieved', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'salesperson_id', 'month_year'], 'kb_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingo_bingo_scores');
    }
};
