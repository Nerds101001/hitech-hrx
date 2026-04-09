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
        Schema::create('order_lines', function (Blueprint $table) {
          $table->id();
          $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
          $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
          $table->integer('quantity');
          $table->decimal('price', 10, 2);
          $table->decimal('total', 10, 2);
          $table->decimal('discount', 10, 2);
          $table->decimal('tax', 10, 2);
          $table->string('notes')->nullable();
          $table->enum('status', ['posted', 'voided', 'none'])->default('posted');

          $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
          $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
          $table->string('tenant_id', 191)->nullable();
          $table->softDeletes();
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
