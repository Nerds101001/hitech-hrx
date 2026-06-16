<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('appraisal_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., FY 2025-26
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active, closed
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
            $table->softDeletes();
            

        });
    }

    public function down()
    {
        Schema::dropIfExists('appraisal_cycles');
    }
};
