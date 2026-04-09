<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN attendance_type ENUM('open','qr_code','dynamic_qr','geofence','ip_address','site','face_recognition','biometric') NOT NULL DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN attendance_type ENUM('open','qr_code','dynamic_qr','geofence','ip_address','site','face_recognition') NOT NULL DEFAULT 'open'");
    }
};
