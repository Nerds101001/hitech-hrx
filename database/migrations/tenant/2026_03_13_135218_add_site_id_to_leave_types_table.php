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
        if (!Schema::hasColumn('leave_types', 'site_id')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->foreignId('site_id')->nullable()->after('id')->constrained('sites')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('leave_types', 'site_id')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropConstrainedForeignId('site_id');
            });
        }
    }

};
