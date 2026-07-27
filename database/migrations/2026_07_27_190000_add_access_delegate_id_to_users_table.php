<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vacant-position access delegation: org-chart placeholder accounts (e.g.
 * "General Manager Export" with no real hire yet) carry a pointer to the
 * real employee who may log in as that position from the header Delegate
 * Access hub and receives its notifications, until the position is filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('access_delegate_id')->nullable()->after('reporting_to_id');
            $table->foreign('access_delegate_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['access_delegate_id']);
            $table->dropColumn('access_delegate_id');
        });
    }
};
