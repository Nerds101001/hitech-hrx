<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_warnings', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('notes');
        });

        Schema::table('employee_complaints', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('employee_warnings', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });

        Schema::table('employee_complaints', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
