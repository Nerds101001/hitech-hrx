<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order an admin puts the Learn @ Hi Tech library videos in (drag and drop
 * on Training Content Management). A video with no row here has not been
 * placed yet and is listed after the placed ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_library_video_positions')) {
            return;
        }

        Schema::create('training_library_video_positions', function (Blueprint $table) {
            $table->id();
            $table->string('library_file_id', 36)->unique();
            $table->unsignedInteger('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_library_video_positions');
    }
};
