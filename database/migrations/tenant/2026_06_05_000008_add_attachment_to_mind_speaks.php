<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mind_speaks', function (Blueprint $table) {
            if (!Schema::hasColumn('mind_speaks', 'attachment')) {
                $table->string('attachment')->nullable()->after('is_anonymous');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mind_speaks', function (Blueprint $table) {
            if (Schema::hasColumn('mind_speaks', 'attachment')) {
                $table->dropColumn('attachment');
            }
        });
    }
};
