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
    Schema::create('sos_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->string('latitude', 191)->nullable();
      $table->string('longitude', 191)->nullable();
      $table->string('address', 1000)->nullable();
      $table->string('notes', 191)->nullable();
      $table->string('img_url', 191)->nullable();
      $table->enum('status', ['pending', 'resolved'])->default('pending');
      $table->dateTime('resolved_at')->nullable();
      $table->foreignId('resolved_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->string('admin_notes', 191)->nullable();

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
    Schema::dropIfExists('sos_logs');
  }
};
