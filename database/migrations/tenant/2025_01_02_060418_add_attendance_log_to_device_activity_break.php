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
    Schema::table('device_status_logs', function (Blueprint $table) {
      $table->after('device_id', function ($table) {
        $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->onDelete('cascade');
        $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade');
      });
    });

    Schema::table('activities', function (Blueprint $table) {
      $table->after('id', function ($table) {
        $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->onDelete('set null');
        $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade');
      });
    });

    Schema::table('attendance_breaks', function (Blueprint $table) {
      $table->after('id', function ($table) {
        $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->onDelete('cascade');
        $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade');
      });
    });

    Schema::table('orders', function (Blueprint $table) {
      $table->after('user_id', function ($table) {
        $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->onDelete('cascade');
        $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade');
      });
    });

    Schema::table('visits', function (Blueprint $table) {
      $table->after('id', function ($table) {
        $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->onDelete('cascade');
        $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade');
      });
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('device_status_logs', function (Blueprint $table) {
      $table->dropForeign(['attendance_log_id']);
      $table->dropColumn('attendance_log_id');
    });

    Schema::table('activities', function (Blueprint $table) {
      $table->dropForeign(['attendance_log_id']);
      $table->dropColumn('attendance_log_id');
    });

    Schema::table('attendance_breaks', function (Blueprint $table) {
      $table->dropForeign(['attendance_log_id']);
      $table->dropColumn('attendance_log_id');
    });
  }
};
