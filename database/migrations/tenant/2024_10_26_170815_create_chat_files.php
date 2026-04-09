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
    Schema::create('chat_files', function (Blueprint $table) {
      $table->id();
      $table->foreignId('chat_id')->constrained('chats')->onDelete('cascade');
      $table->foreignId('chat_message_id')->nullable()->constrained()->onDelete('cascade');
      $table->foreignId('uploaded_by_id')->constrained('users')->onDelete('cascade');
      $table->string('file_path');
      $table->string('file_type')->nullable(); // e.g., 'image', 'document', etc.
      $table->string('file_name')->nullable();
      $table->string('file_extension', 10)->nullable();
      $table->string('file_size', 191)->nullable();

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
    Schema::dropIfExists('chat_files');
  }
};
