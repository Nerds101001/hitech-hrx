<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ninety_day_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('appraisal_cycle_id')->nullable();
            
            $table->string('client_name');
            $table->string('product')->nullable();
            $table->string('goal_type')->default('Order');
            $table->decimal('target_value', 15, 2)->default(0);
            
            $table->string('w0')->nullable();
            $table->string('w1')->nullable();
            $table->string('w2')->nullable();
            $table->string('w3')->nullable();
            $table->string('w4')->nullable();
            $table->string('w5')->nullable();
            $table->string('w6')->nullable();
            $table->string('w7')->nullable();
            $table->string('w8')->nullable();
            $table->string('w9')->nullable();
            $table->string('w10')->nullable();
            $table->string('w11')->nullable();
            $table->string('w12')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('appraisal_cycle_id')->references('id')->on('appraisal_cycles')->onDelete('set null');

            $table->foreign('created_by_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ninety_day_goals');
    }
};
