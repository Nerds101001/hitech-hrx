<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
          $table->foreignId('geofence_group_id')->nullable()->constrained('geofence_groups')->onDelete('set null');

          $table->foreignId('ip_address_group_id')->nullable()->constrained('ip_address_groups')->onDelete('set null');

          $table->foreignId('qr_group_id')->nullable()->constrained('qr_groups')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
          $table->dropForeign(['geofence_group_id']);
          $table->dropColumn('geofence_group_id');

          $table->dropForeign(['ip_address_group_id']);
          $table->dropColumn('ip_address_group_id');

          $table->dropForeign(['qr_group_id']);
          $table->dropColumn('qr_group_id');
        });
    }
};
