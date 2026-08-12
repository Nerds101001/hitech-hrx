<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uniform requests — an employee asks for a shirt or t-shirt, admin/HR approves
 * or rejects with a remark.
 *
 * Deliberately NOT hung off the assets table: shirts are consumables issued to a
 * person, not tracked equipment with a serial number and a current value. Mixing
 * them in would put clothing into the asset register and its depreciation.
 *
 * Column shape follows sales_pipeline_delete_requests, which is this codebase's
 * established request/approval pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('uniform_requests')) {
            return;
        }

        Schema::create('uniform_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('item_type', 30);              // shirt | tshirt
            $table->string('size', 10);                   // S | M | L | XL | XXL | XXXL
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('remarks')->nullable();          // the employee's own note

            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->text('decision_note')->nullable();    // admin's remark on approve OR reject

            $table->string('tenant_id', 191)->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_requests');
    }
};
