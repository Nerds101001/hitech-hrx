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
        // 1. Create salary_policies table
        Schema::create('salary_policies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 191)->nullable()->index();
            $table->string('name');
            $table->foreignId('site_id')->nullable()->constrained('sites')->onDelete('set null');
            $table->string('month_calculation_mode')->default('actual_calendar_days'); // actual_calendar_days, fixed_30_days
            $table->decimal('basic_percentage', 5, 2)->default(50.00);
            $table->decimal('hra_percentage', 5, 2)->default(25.00);
            $table->decimal('ca_fixed', 10, 2)->default(0.00);
            $table->decimal('medical_fixed', 10, 2)->default(0.00);
            $table->decimal('edu_fixed', 10, 2)->default(0.00);
            $table->decimal('pt_threshold', 10, 2)->default(20833.00);
            $table->decimal('pt_amount', 10, 2)->default(200.00);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Add columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('salary_policy_id')->nullable()->constrained('salary_policies')->onDelete('set null');
            $table->decimal('custom_basic', 10, 2)->nullable();
            $table->decimal('custom_hra', 10, 2)->nullable();
            $table->decimal('custom_ca', 10, 2)->nullable();
            $table->decimal('custom_medical', 10, 2)->nullable();
            $table->decimal('custom_edu', 10, 2)->nullable();
            $table->decimal('custom_special_allowance', 10, 2)->nullable();
            $table->decimal('custom_pt', 10, 2)->nullable();
        });

        // 3. Add columns to payslips table
        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('hra', 10, 2)->default(0.00);
            $table->decimal('ca', 10, 2)->default(0.00);
            $table->decimal('medical', 10, 2)->default(0.00);
            $table->decimal('edu', 10, 2)->default(0.00);
            $table->decimal('special_allowance', 10, 2)->default(0.00);
            $table->decimal('pt', 10, 2)->default(0.00);
            $table->string('month_calculation_mode')->nullable();
        });

        // 4. Add columns to payroll_records table
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->decimal('hra', 10, 2)->default(0.00);
            $table->decimal('ca', 10, 2)->default(0.00);
            $table->decimal('medical', 10, 2)->default(0.00);
            $table->decimal('edu', 10, 2)->default(0.00);
            $table->decimal('special_allowance', 10, 2)->default(0.00);
            $table->decimal('pt', 10, 2)->default(0.00);
            $table->string('month_calculation_mode')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn(['hra', 'ca', 'medical', 'edu', 'special_allowance', 'pt', 'month_calculation_mode']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['hra', 'ca', 'medical', 'edu', 'special_allowance', 'pt', 'month_calculation_mode']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_policy_id');
            $table->dropColumn(['custom_basic', 'custom_hra', 'custom_ca', 'custom_medical', 'custom_edu', 'custom_special_allowance', 'custom_pt']);
        });

        Schema::dropIfExists('salary_policies');
    }
};
