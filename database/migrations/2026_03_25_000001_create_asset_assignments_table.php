<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('returned_at')->nullable();
            $table->string('condition_on_assignment')->nullable();
            $table->string('condition_on_return')->nullable();
            $table->text('notes')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_assignments');
    }
};
