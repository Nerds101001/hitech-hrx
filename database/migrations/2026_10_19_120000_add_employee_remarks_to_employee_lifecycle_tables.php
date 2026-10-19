<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the employee a warning/complaint concerns post a reply from the
 * "Read more" popup — not just acknowledge in silence. HR/Admin see it on
 * the same record, in their own popup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_warnings', function (Blueprint $table) {
            $table->text('employee_remarks')->nullable()->after('acknowledged_at');
            $table->timestamp('employee_remarks_at')->nullable()->after('employee_remarks');
        });

        Schema::table('employee_complaints', function (Blueprint $table) {
            $table->text('employee_remarks')->nullable()->after('acknowledged_at');
            $table->timestamp('employee_remarks_at')->nullable()->after('employee_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('employee_warnings', function (Blueprint $table) {
            $table->dropColumn(['employee_remarks', 'employee_remarks_at']);
        });

        Schema::table('employee_complaints', function (Blueprint $table) {
            $table->dropColumn(['employee_remarks', 'employee_remarks_at']);
        });
    }
};
