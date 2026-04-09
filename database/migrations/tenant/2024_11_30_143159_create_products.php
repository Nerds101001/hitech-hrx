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
        Schema::create('products', function (Blueprint $table) {
          $table->id();
          $table->string('name');
          $table->text('description')->nullable();
          $table->string('product_code')->unique();
          $table->enum('status', ['active', 'inactive'])->default('active');
          $table->foreignId('category_id')->constrained('product_categories')->onDelete('cascade');
          $table->decimal('base_price', 10, 2);
          $table->decimal('discount', 10, 2)->nullable();
          $table->decimal('tax', 10, 2)->nullable();
          $table->decimal('price', 10, 2);
          $table->integer('stock')->nullable();
          $table->text('images')->nullable();
          $table->text('thumbnail')->nullable();

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
        Schema::dropIfExists('products');
    }
};
