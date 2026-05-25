<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_requests', 'is_lwp')) {
                $table->boolean('is_lwp')->default(false)->after('status')
                      ->comment('Leave Without Pay — true when employee has insufficient balance');
            }
            if (!Schema::hasColumn('leave_requests', 'lwp_days')) {
                $table->float('lwp_days')->default(0)->after('is_lwp')
                      ->comment('Number of days that are LWP (may be partial)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['is_lwp', 'lwp_days']);
        });
    }
};
