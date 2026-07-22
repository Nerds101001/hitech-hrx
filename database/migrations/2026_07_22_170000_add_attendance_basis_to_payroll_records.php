<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PayrollService writes total_worked_days, total_absent_days and paid_days on
 * every record, but the columns never existed, so the values were silently
 * dropped. A payslip therefore carried no evidence of how its amount was
 * reached — you could not tell whether someone was paid 30 days or 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_records', 'paid_days')) {
                $table->decimal('paid_days', 6, 2)->nullable()->after('period');
            }
            if (!Schema::hasColumn('payroll_records', 'total_worked_days')) {
                $table->decimal('total_worked_days', 6, 2)->nullable()->after('paid_days');
            }
            if (!Schema::hasColumn('payroll_records', 'total_absent_days')) {
                $table->decimal('total_absent_days', 6, 2)->nullable()->after('total_worked_days');
            }
            if (!Schema::hasColumn('payroll_records', 'employed_days')) {
                $table->decimal('employed_days', 6, 2)->nullable()->after('total_absent_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn(['paid_days', 'total_worked_days', 'total_absent_days', 'employed_days']);
        });
    }
};
