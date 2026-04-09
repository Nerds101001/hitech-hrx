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
    Schema::create('dynamic_qr_devices', function (Blueprint $table) {
      $table->id();
      $table->string('name', 150);
      $table->string('code', 191)->nullable();
      $table->string('description', 250)->nullable();
      $table->string('unique_id', 12)->nullable()->unique();
      $table->string('pin', 6)->nullable();
      $table->string('qr_value', 250)->nullable();
      $table->string('token', 500)->nullable();
      $table->dateTime('qr_last_updated_at')->nullable();
      $table->integer('qr_update_interval')->nullable();
      $table->dateTime('qr_expiry_date')->nullable();

      $table->enum('status', ['new', 'in_use', 'free', 'deactivated'])->default('new');
      $table->enum('device_type', ['android', 'ios', 'windows', 'mac', 'linux', 'web', 'other'])->default('other');

      $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

      $table->string('tenant_id', 191)->nullable();
      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->timestamps();
      $table->softDeletes();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('dynamic_qr_devices');
  }
};
