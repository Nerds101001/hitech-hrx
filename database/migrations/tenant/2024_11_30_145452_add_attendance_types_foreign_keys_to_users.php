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
      $table->foreignId('geofence_group_id')->nullable()->constrained('geofence_groups')->onDelete('cascade');
      $table->foreignId('ip_address_group_id')->nullable()->constrained('ip_address_groups')->onDelete('cascade');
      $table->foreignId('qr_group_id')->nullable()->constrained('qr_groups')->onDelete('cascade');
      $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('cascade');

      $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('cascade');
      $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropForeign(['geofence_group_id']);
      $table->dropColumn('geofence_group_id');

      $table->dropForeign(['ip_address_group_id']);
      $table->dropColumn('ip_address_group_id');

      $table->dropForeign(['qr_group_id']);
      $table->dropColumn('qr_group_id');

      $table->dropForeign(['site_id']);
      $table->dropColumn('site_id');
    });
  }
};
