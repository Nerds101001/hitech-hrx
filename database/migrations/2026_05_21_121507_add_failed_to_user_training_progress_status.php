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
        Schema::table('user_training_progress', function (Blueprint $table) {
            DB::statement("ALTER TABLE user_training_progress MODIFY COLUMN status ENUM('locked', 'available', 'in_progress', 'completed', 'failed') NOT NULL DEFAULT 'locked'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_training_progress', function (Blueprint $table) {
            DB::statement("ALTER TABLE user_training_progress MODIFY COLUMN status ENUM('locked', 'available', 'in_progress', 'completed') NOT NULL DEFAULT 'locked'");
        });
    }
};
