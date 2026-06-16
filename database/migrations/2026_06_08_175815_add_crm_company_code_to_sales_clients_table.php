<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sales_clients', function (Blueprint $table) {
            $table->string('crm_company_code')->nullable()->unique()->after('id');
        });
    }

    public function down()
    {
        Schema::table('sales_clients', function (Blueprint $table) {
            $table->dropColumn('crm_company_code');
        });
    }
};

