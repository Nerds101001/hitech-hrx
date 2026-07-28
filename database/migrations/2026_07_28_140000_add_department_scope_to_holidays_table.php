<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department-scoped holidays.
 *
 * A holiday row already narrows by location (site_id). Some holidays are
 * really department affairs though — a Production shutdown day, or a Sales-only
 * concession — so a nullable department_id lets HR narrow that way too.
 *
 * NULL on either column means "not restricted by that dimension":
 *   site NULL + dept NULL  -> everyone
 *   site set  + dept NULL  -> everyone at that location
 *   site NULL + dept set   -> that department, every location
 *   both set               -> that department AT that location
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('site_id');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
