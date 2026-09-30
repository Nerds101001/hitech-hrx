<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is allowed to fill a KPI on the whole team's behalf.
 *
 * The manual KPIs were designed as self-reporting: each person enters their
 * own Freight Additions, Quotations, Calls and so on, and HR approves each
 * number. In practice that does not happen -- in September only 5 of 17 New
 * Biz staff filled anything, leaving 473 marks unclaimed, and of those that
 * did, only one person's entries were ever approved.
 *
 * Several of these figures are not really the salesperson's to report anyway:
 * call hours come off the GSM gateway and quotation counts come out of the
 * CRM, so one person holds the real number for everybody. This table records
 * that: a row means "this user may enter this KPI for every assignee".
 *
 * An owner's figure is authoritative and needs no approval -- they are the
 * source, so there is nobody better placed to sign it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_data_owners', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('kpi_template_id');
            $table->unsignedBigInteger('user_id');

            // Optional narrowing: without it the owner fills this KPI for every
            // assignee, with it only for that department's people. Lets Calls
            // Discipline have one owner for New Biz and another for Sales.
            $table->unsignedBigInteger('department_id')->nullable();

            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->unique(['kpi_template_id', 'user_id', 'department_id'], 'kpi_data_owner_unique');
            $table->index(['user_id', 'kpi_template_id']);

            $table->foreign('kpi_template_id')->references('id')->on('kpi_templates')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_data_owners');
    }
};
