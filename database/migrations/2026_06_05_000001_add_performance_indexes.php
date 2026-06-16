<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance Indexes Migration
 * Adds indexes on the most frequently filtered columns across the core HR tables.
 * These eliminate full-table scans on attendance reports, leave lists, and user lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ──────────────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            // Status is filtered on almost every query (active employees, etc.)
            if (!$this->indexExists('users', 'users_status_index')) {
                $table->index('status', 'users_status_index');
            }
            // date_of_joining: used in hiring trend charts and anniversary queries
            if (!$this->indexExists('users', 'users_date_of_joining_index')) {
                $table->index('date_of_joining', 'users_date_of_joining_index');
            }
            // department_id: used in department distribution and employee filtering
            if (!$this->indexExists('users', 'users_department_id_index')) {
                $table->index('department_id', 'users_department_id_index');
            }
            // reporting_to_id: used to find direct reports for manager dashboards
            if (!$this->indexExists('users', 'users_reporting_to_id_index')) {
                $table->index('reporting_to_id', 'users_reporting_to_id_index');
            }
            // relieved_at: used in attrition trend charts
            if (!$this->indexExists('users', 'users_relieved_at_index')) {
                $table->index('relieved_at', 'users_relieved_at_index');
            }
            // probation_end_date: used in probation dashboard widget
            if (!$this->indexExists('users', 'users_probation_end_date_index')) {
                $table->index('probation_end_date', 'users_probation_end_date_index');
            }
        });

        // ── attendances ────────────────────────────────────────────────────────
        Schema::table('attendances', function (Blueprint $table) {
            // check_in_time: the most queried column — used in every attendance report
            if (!$this->indexExists('attendances', 'attendances_check_in_time_index')) {
                $table->index('check_in_time', 'attendances_check_in_time_index');
            }
            // status: filtered on (present/absent/late) in every attendance listing
            if (!$this->indexExists('attendances', 'attendances_status_index')) {
                $table->index('status', 'attendances_status_index');
            }
            // Composite: the most common query pattern is user_id + date
            if (!$this->indexExists('attendances', 'attendances_user_date_index')) {
                $table->index(['user_id', 'check_in_time'], 'attendances_user_date_index');
            }
        });

        // ── leave_requests ─────────────────────────────────────────────────────
        Schema::table('leave_requests', function (Blueprint $table) {
            // status: pending/approved/rejected filter used everywhere
            if (!$this->indexExists('leave_requests', 'leave_requests_status_index')) {
                $table->index('status', 'leave_requests_status_index');
            }
            // from_date + to_date: used in "who is on leave today" queries
            if (!$this->indexExists('leave_requests', 'leave_requests_dates_index')) {
                $table->index(['from_date', 'to_date'], 'leave_requests_dates_index');
            }
            // user_id: used in every employee's leave history query
            if (!$this->indexExists('leave_requests', 'leave_requests_user_id_index')) {
                $table->index('user_id', 'leave_requests_user_id_index');
            }
        });

        // ── expense_requests ───────────────────────────────────────────────────
        Schema::table('expense_requests', function (Blueprint $table) {
            if (!$this->indexExists('expense_requests', 'expense_requests_status_index')) {
                $table->index('status', 'expense_requests_status_index');
            }
            if (!$this->indexExists('expense_requests', 'expense_requests_user_id_index')) {
                $table->index('user_id', 'expense_requests_user_id_index');
            }
        });

        // ── document_requests ──────────────────────────────────────────────────
        Schema::table('document_requests', function (Blueprint $table) {
            if (!$this->indexExists('document_requests', 'document_requests_status_index')) {
                $table->index('status', 'document_requests_status_index');
            }
            if (!$this->indexExists('document_requests', 'document_requests_user_id_index')) {
                $table->index('user_id', 'document_requests_user_id_index');
            }
        });

        // ── loan_requests ──────────────────────────────────────────────────────
        Schema::table('loan_requests', function (Blueprint $table) {
            if (!$this->indexExists('loan_requests', 'loan_requests_status_index')) {
                $table->index('status', 'loan_requests_status_index');
            }
            if (!$this->indexExists('loan_requests', 'loan_requests_user_id_index')) {
                $table->index('user_id', 'loan_requests_user_id_index');
            }
        });

        // ── job_applications ───────────────────────────────────────────────────
        Schema::table('job_applications', function (Blueprint $table) {
            if (!$this->indexExists('job_applications', 'job_applications_created_at_index')) {
                $table->index('created_at', 'job_applications_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndexIfExists('users_status_index');
            $table->dropIndexIfExists('users_date_of_joining_index');
            $table->dropIndexIfExists('users_department_id_index');
            $table->dropIndexIfExists('users_reporting_to_id_index');
            $table->dropIndexIfExists('users_relieved_at_index');
            $table->dropIndexIfExists('users_probation_end_date_index');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndexIfExists('attendances_check_in_time_index');
            $table->dropIndexIfExists('attendances_status_index');
            $table->dropIndexIfExists('attendances_user_date_index');
        });
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndexIfExists('leave_requests_status_index');
            $table->dropIndexIfExists('leave_requests_dates_index');
            $table->dropIndexIfExists('leave_requests_user_id_index');
        });
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropIndexIfExists('expense_requests_status_index');
            $table->dropIndexIfExists('expense_requests_user_id_index');
        });
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndexIfExists('document_requests_status_index');
            $table->dropIndexIfExists('document_requests_user_id_index');
        });
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropIndexIfExists('loan_requests_status_index');
            $table->dropIndexIfExists('loan_requests_user_id_index');
        });
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropIndexIfExists('job_applications_created_at_index');
        });
    }

    /** Check if an index already exists to avoid duplicate-key errors on re-run */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
