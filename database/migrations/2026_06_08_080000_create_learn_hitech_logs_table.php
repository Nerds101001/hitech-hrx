<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('learn_hitech_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('library_file_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('awarded_by_id')->nullable();
            $table->integer('awarded_points')->default(5);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('learn_hitech_logs');
    }
};
