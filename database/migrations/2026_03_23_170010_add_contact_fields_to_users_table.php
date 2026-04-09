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
        Schema::table('users', function (Blueprint $バランス) {
            $バランス->string('personal_email')->nullable()->after('email');
            $バランス->string('official_phone')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $バランス) {
            $バランス->dropColumn(['personal_email', 'official_phone']);
        });
    }
};
