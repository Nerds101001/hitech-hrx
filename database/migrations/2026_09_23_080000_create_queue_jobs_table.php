<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's queue table is normally called "jobs" — but this app already has an unrelated
     * "jobs" table (recruitment job postings), so the database queue connection is pointed at
     * "queue_jobs" instead (see DB_QUEUE_TABLE in .env / phpunit.xml) to avoid colliding with it.
     */
    public function up(): void
    {
        Schema::create('queue_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_jobs');
    }
};
