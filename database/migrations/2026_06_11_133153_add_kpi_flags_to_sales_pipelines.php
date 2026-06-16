<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->boolean('razor_blade')->default(false)->after('product');
            $table->boolean('upgrade')->default(false)->after('razor_blade');
            $table->boolean('rate_increase')->default(false)->after('upgrade');
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropColumn(['razor_blade', 'upgrade', 'rate_increase']);
        });
    }
};
