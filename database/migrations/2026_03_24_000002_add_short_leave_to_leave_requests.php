<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('is_short_leave')->default(false)->after('leave_type_id');
            $table->decimal('duration_hours', 4, 2)->nullable()->after('is_short_leave');
            $table->time('start_time')->nullable()->after('duration_hours');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['is_short_leave', 'duration_hours', 'start_time', 'end_time']);
        });
    }
};
