<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category_id')->nullable();
            $table->string('assigned_to')->nullable();
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 10, 2)->default(0);
            $table->decimal('current_value', 10, 2)->default(0);
            $table->string('status')->default('available');
            $table->string('location')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'assigned_to']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assets');
    }
};
