<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_outdoor_duty')->default(false)->after('leave_request_id');
            $table->foreignId('outdoor_duty_id')->nullable()->constrained('outdoor_duties')->onDelete('set null')->after('is_outdoor_duty');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['outdoor_duty_id']);
            $table->dropColumn(['is_outdoor_duty', 'outdoor_duty_id']);
        });
    }
};
