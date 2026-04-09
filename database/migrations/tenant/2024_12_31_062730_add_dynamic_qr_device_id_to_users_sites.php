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
    Schema::table('users', function (Blueprint $table) {
      $table->foreignId('dynamic_qr_device_id')->nullable()->constrained('dynamic_qr_devices')->nullOnDelete();
    });

    Schema::table('sites', function (Blueprint $table) {
      $table->foreignId('dynamic_qr_device_id')->nullable()->constrained('dynamic_qr_devices')->nullOnDelete();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropForeign(['dynamic_qr_device_id']);
      $table->dropColumn('dynamic_qr_device_id');
    });

    Schema::table('sites', function (Blueprint $table) {
      $table->dropForeign(['dynamic_qr_device_id']);
      $table->dropColumn('dynamic_qr_device_id');
    });
  }
};
