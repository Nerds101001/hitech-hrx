<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['check_in_time', 'status'], 'idx_attendances_checkin_status');
            $table->index(['user_id', 'check_in_time'], 'idx_attendances_user_checkin');
            $table->index('tenant_id', 'idx_attendances_tenant_id');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index(['status', 'from_date', 'to_date'], 'idx_leavereq_status_dates');
            $table->index(['user_id', 'status'], 'idx_leavereq_user_status');
            $table->index('tenant_id', 'idx_leavereq_tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('status', 'idx_users_status');
            $table->index(['department_id', 'status'], 'idx_users_dept_status');
            $table->index(['designation_id', 'status'], 'idx_users_desig_status');
            $table->index(['tenant_id', 'status'], 'idx_users_tenant_status');
            $table->index('reporting_to_id', 'idx_users_reporting_to');
            $table->index('date_of_joining', 'idx_users_doj');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_payslips_user_status');
            $table->index('payroll_record_id', 'idx_payslips_payroll_rec');
            $table->index('tenant_id', 'idx_payslips_tenant_id');
        });

        Schema::table('payroll_records', function (Blueprint $table) {
            $table->index(['period', 'status'], 'idx_payroll_rec_period_status');
            $table->index('tenant_id', 'idx_payroll_rec_tenant_id');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->index(['status', 'user_id'], 'idx_docreq_status_user');
            $table->index('generated_file', 'idx_docreq_generated_file');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'read_at'], 'idx_notif_notifiable_read');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notif_type_id_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_checkin_status');
            $table->dropIndex('idx_attendances_user_checkin');
            $table->dropIndex('idx_attendances_tenant_id');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('idx_leavereq_status_dates');
            $table->dropIndex('idx_leavereq_user_status');
            $table->dropIndex('idx_leavereq_tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_status');
            $table->dropIndex('idx_users_dept_status');
            $table->dropIndex('idx_users_desig_status');
            $table->dropIndex('idx_users_tenant_status');
            $table->dropIndex('idx_users_reporting_to');
            $table->dropIndex('idx_users_doj');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex('idx_payslips_user_status');
            $table->dropIndex('idx_payslips_payroll_rec');
            $table->dropIndex('idx_payslips_tenant_id');
        });

        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_rec_period_status');
            $table->dropIndex('idx_payroll_rec_tenant_id');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndex('idx_docreq_status_user');
            $table->dropIndex('idx_docreq_generated_file');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notif_notifiable_read');
            $table->dropIndex('idx_notif_type_id_read');
        });
    }
};
