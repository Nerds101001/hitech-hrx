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
    Schema::create('chat_messages', function (Blueprint $table) {
      $table->id();
      $table->foreignId('chat_id')->constrained()->onDelete('cascade');
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->text('content')->nullable(); // Message content
      $table->boolean('is_edited')->default(false);
      $table->boolean('is_forwarded')->default(false);
      $table->string('message_type')->default('text'); // e.g., 'text', 'image', 'file'

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
    Schema::dropIfExists('chat_messages');
  }
};
