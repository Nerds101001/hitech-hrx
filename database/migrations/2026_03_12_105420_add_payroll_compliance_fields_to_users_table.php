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
        Schema::table('users', function (Blueprint $table) {
            $table->string('pf_no')->nullable()->after('pan_no');
            $table->string('esi_no')->nullable()->after('pf_no');
            $table->string('uan_no')->nullable()->after('esi_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pf_no', 'esi_no', 'uan_no']);
        });
    }
};
