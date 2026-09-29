<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who has watched which Digital Library video as part of training (the
 * Learn @ Hi Tech phase lists library videos live instead of its own modules).
 * started_at is the first time the video was opened; completed_at is set when
 * "Mark as watched" is accepted after the minimum watch time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_video_views')) {
            return;
        }

        Schema::create('training_video_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('library_file_id', 36);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'library_file_id']);
            $table->index('library_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_video_views');
    }
};
