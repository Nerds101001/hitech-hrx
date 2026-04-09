<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        Schema::table('asset_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_categories', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
