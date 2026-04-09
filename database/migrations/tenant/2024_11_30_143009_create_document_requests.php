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
    Schema::create('document_requests', function (Blueprint $table) {
      $table->id();
      $table->string('remarks')->nullable();
      $table->foreignId('document_type_id')->constrained('document_types')->onDelete('cascade');
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->enum('status', ['pending', 'approved', 'rejected', 'generated', 'cancelled'])->default('pending');
      $table->string('admin_remarks')->nullable();
      $table->text('generated_file')->nullable();

      $table->foreignId('action_taken_by_id')->nullable()->constrained('users')->onDelete('cascade');
      $table->date('action_taken_at')->nullable();

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
    Schema::dropIfExists('document_request');
  }
};
