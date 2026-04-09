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
    Schema::create('expense_requests', function (Blueprint $table) {
      $table->id();
      $table->dateTime('for_date');
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->foreignId('expense_type_id')->constrained('expense_types')->onDelete('cascade');
      $table->text('document_url')->nullable();
      $table->string('remarks', 500)->nullable();
      $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
      $table->dateTime('approved_at')->nullable();
      $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->text('admin_remarks')->nullable();
      $table->decimal('amount', 10, 2);
      $table->decimal('approved_amount', 10, 2)->nullable();

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
    Schema::dropIfExists('expense_requests');
  }
};
