<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cc_salesperson_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cc_user_id');
            $table->foreign('cc_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('sales_user_id');
            $table->foreign('sales_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->unique(['cc_user_id', 'sales_user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('cc_salesperson_map'); }
};
