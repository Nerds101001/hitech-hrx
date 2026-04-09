<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('user_statuses', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->enum('status', [UserStatus::ONLINE->value, UserStatus::OFFLINE->value, UserStatus::BUSY->value,
        UserStatus::AWAY->value, UserStatus::ON_CALL->value, UserStatus::DO_NOT_DISTURB->value,
        UserStatus::ON_LEAVE->value, UserStatus::ON_MEETING->value, UserStatus::UNKNOWN->value]);
      $table->string('message')->nullable();
      $table->timestamp('expires_at')->nullable();

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
    Schema::dropIfExists('user_statuses');
  }
};
