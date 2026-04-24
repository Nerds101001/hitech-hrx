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
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'leave_request_id')) {
                $table->unsignedBigInteger('leave_request_id')->nullable()->after('shift_id');
                $table->foreign('leave_request_id')->references('id')->on('leave_requests')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
            $table->dropColumn('leave_request_id');
        });
    }
};
