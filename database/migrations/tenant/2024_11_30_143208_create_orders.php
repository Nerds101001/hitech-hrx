<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('orders', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
      $table->string('order_no')->unique();
      $table->decimal('total', 10, 2);
      $table->decimal('discount', 10, 2)->default(0);
      $table->decimal('tax', 10, 2);
      $table->decimal('grand_total', 10, 2);
      $table->integer('quantity');
      $table->string('notes')->nullable();
      $table->string('user_remarks')->nullable();
      $table->string('admin_remarks')->nullable();
      $table->string('cancel_remarks')->nullable();

      $table->foreignId('processed_by_id')->nullable()->constrained('users')->onDelete('cascade');
      $table->dateTime('processed_at')->nullable();
      $table->foreignId('completed_by_id')->nullable()->constrained('users')->onDelete('cascade');
      $table->dateTime('completed_at')->nullable();
      $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->onDelete('cascade');
      $table->dateTime('cancelled_at')->nullable();
      $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');

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
    Schema::dropIfExists('orders');
  }
};
