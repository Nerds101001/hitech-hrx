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
        // Change enum to string for flexibility in statuses
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->change();
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->string('status', 50)->default('generated')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting back to enum is tricky and risky for data, usually avoided in string-conversions
        // But for consistency:
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->enum('status', ['pending', 'completed', 'paid', 'cancelled'])->default('pending')->change();
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->enum('status', ['generated', 'delivered', 'archived'])->default('generated')->change();
        });
    }
};
