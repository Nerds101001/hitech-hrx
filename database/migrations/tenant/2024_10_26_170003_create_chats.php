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
    Schema::create('chats', function (Blueprint $table) {
      $table->id();
      $table->boolean('is_group_chat')->default(false);
      $table->string('name')->nullable()->comment('Group chat name');
      $table->string('image')->nullable()->comment('Group chat image');
      $table->string('description')->nullable()->comment('Group chat description');

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
    Schema::dropIfExists('chats');
  }
};
