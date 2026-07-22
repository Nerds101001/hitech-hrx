<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The attendance basis lives on the PAYSLIP, not the payroll record —
 * total_worked_days, total_absent_days, total_holidays and total_weekends were
 * already there and were always being saved. Only the two figures that decide
 * the amount were absent: how many days the person was employed in the period,
 * and how many of those were paid.
 *
 * Also drops the four columns added to payroll_records earlier today, which
 * nothing writes to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payslips', 'paid_days')) {
                $table->decimal('paid_days', 6, 2)->nullable()->after('total_absent_days');
            }
            if (!Schema::hasColumn('payslips', 'employed_days')) {
                $table->decimal('employed_days', 6, 2)->nullable()->after('paid_days');
            }
        });

        Schema::table('payroll_records', function (Blueprint $table) {
            foreach (['paid_days', 'total_worked_days', 'total_absent_days', 'employed_days'] as $col) {
                if (Schema::hasColumn('payroll_records', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['paid_days', 'employed_days']);
        });
    }
};
