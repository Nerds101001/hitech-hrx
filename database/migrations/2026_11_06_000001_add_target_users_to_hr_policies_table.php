<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_policies', function (Blueprint $table) {
            $table->json('target_users')->nullable()->after('target_departments');
        });
    }

    public function down(): void
    {
        Schema::table('hr_policies', function (Blueprint $table) {
            $table->dropColumn('target_users');
        });
    }
};
