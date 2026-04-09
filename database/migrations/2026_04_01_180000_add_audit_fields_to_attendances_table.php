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
            if (!Schema::hasColumn('attendances', 'admin_reason')) {
                $table->text('admin_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('attendances', 'updated_by_id')) {
                $table->unsignedBigInteger('updated_by_id')->nullable()->after('admin_reason');
                $table->foreign('updated_by_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'updated_by_id')) {
                $table->dropForeign(['updated_by_id']);
                $table->dropColumn('updated_by_id');
            }
            if (Schema::hasColumn('attendances', 'admin_reason')) {
                $table->dropColumn('admin_reason');
            }
        });
    }
};
