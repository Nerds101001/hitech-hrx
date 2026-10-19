<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * employee_terminations.termination_type was a MySQL ENUM('misconduct',
 * 'performance','redundancy','contract_end') — a different, non-overlapping
 * vocabulary from App\Enums\TerminationType ('resignation',
 * 'resignation_without_notice_period','terminated_with_cause',
 * 'terminated_without_cause','layoff','retirement','probation_failed'),
 * which is what the termination form actually validates against and sends.
 * Every termination has been failing at this insert since the feature
 * shipped — the table had zero rows on prod. users.termination_type is
 * already a plain varchar(255) for the same field; match that here instead
 * of hand-maintaining a second enum that has to be kept in sync with the
 * PHP one by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_terminations')) {
            return;
        }

        DB::statement("ALTER TABLE employee_terminations MODIFY termination_type VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_terminations')) {
            return;
        }

        DB::statement("ALTER TABLE employee_terminations MODIFY termination_type ENUM('misconduct','performance','redundancy','contract_end') NOT NULL");
    }
};
