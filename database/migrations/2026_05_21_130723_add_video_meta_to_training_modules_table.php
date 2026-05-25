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
        Schema::table('training_modules', function (Blueprint $table) {
            $table->json('video_chapters')->nullable()->after('show_all_at_once');
            $table->json('video_milestones')->nullable()->after('video_chapters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropColumn(['video_chapters', 'video_milestones']);
        });
    }
};
