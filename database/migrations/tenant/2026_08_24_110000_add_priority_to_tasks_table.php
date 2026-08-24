<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How urgent a task is, set by whoever assigns it. Drives the priority badge
 * and filter on both Task Management and My Tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
