<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval-gated customer deletion.
 *
 * Deleting a customer removes every monthly figure attached to it and shifts
 * reported totals, and it cannot be undone. Rather than let the sales floor
 * delete directly, a request is raised here with a reason and it only becomes
 * a real deletion once admin/HR approve it.
 *
 * `party_name` and `book` are SNAPSHOTS: once the approval goes through the
 * sales_pipelines row is gone, so the history tab would otherwise show a blank
 * row. Keeping a copy means the approved list stays readable forever.
 *
 * Covers BOTH books — Sales Report (is_locked = 1) and Sales Pipeline
 * (is_locked = 0) — since they are the same table and the same endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_pipeline_delete_requests', function (Blueprint $table) {
            $table->id();

            // Nullable + nullOnDelete: if the customer disappears by another
            // route, the request row survives as history instead of vanishing.
            $table->foreignId('sales_pipeline_id')->nullable()
                  ->constrained('sales_pipelines')->nullOnDelete();

            $table->string('party_name');              // snapshot
            $table->string('book', 20)->default('report'); // report | pipeline (snapshot)

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 30);              // duplicate | legal_case | closed
            $table->text('remarks')->nullable();       // requester's own note

            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->text('decision_note')->nullable(); // why admin/HR rejected

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            // One open request per customer — stops the same row being queued twice.
            $table->index(['sales_pipeline_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_pipeline_delete_requests');
    }
};
