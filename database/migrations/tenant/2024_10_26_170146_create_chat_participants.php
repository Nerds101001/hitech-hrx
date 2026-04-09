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
    Schema::create('chat_participants', function (Blueprint $table) {
      $table->id();
      $table->foreignId('chat_id')->constrained()->onDelete('cascade');
      $table->foreignId('user_id')->constrained()->onDelete('cascade');

      $table->boolean('is_admin')->default(false);

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
    Schema::dropIfExists('chat_participants');
  }
};
