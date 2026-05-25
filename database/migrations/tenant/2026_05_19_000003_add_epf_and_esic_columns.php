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
        Schema::table('salary_policies', function (Blueprint $table) {
            $table->boolean('is_epf_applicable')->default(false);
            $table->decimal('epf_rate', 5, 2)->default(12.00);
            $table->boolean('is_esic_applicable')->default(false);
            $table->decimal('esic_rate', 5, 2)->default(0.75);
            $table->decimal('esic_threshold', 10, 2)->default(21000.00);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('custom_epf', 10, 2)->nullable();
            $table->decimal('custom_esic', 10, 2)->nullable();
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('epf', 10, 2)->default(0.00);
            $table->decimal('esic', 10, 2)->default(0.00);
        });

        Schema::table('payroll_records', function (Blueprint $table) {
            $table->decimal('epf', 10, 2)->default(0.00);
            $table->decimal('esic', 10, 2)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn(['epf', 'esic']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['epf', 'esic']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['custom_epf', 'custom_esic']);
        });

        Schema::table('salary_policies', function (Blueprint $table) {
            $table->dropColumn(['is_epf_applicable', 'epf_rate', 'is_esic_applicable', 'esic_rate', 'esic_threshold']);
        });
    }
};
