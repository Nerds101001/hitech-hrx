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
        Schema::create('travel_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('claim_month'); // e.g. "May 2026" or "2026-05"
            $table->string('company')->default('Hi Tech International');
            $table->decimal('sales_collection', 15, 2)->default(0);
            
            $table->string('status')->default('draft'); // draft, submitted, verified, approved, paid, rejected
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_advances', 12, 2)->default(0);
            $table->decimal('net_payable', 12, 2)->default(0);
            $table->boolean('late_penalty_applied')->default(false);
            
            // Banking Details
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc')->nullable();
            
            // Workflow details
            $table->foreignId('verifier_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('payer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            
            // Split Payments
            $table->decimal('split_85_amount', 12, 2)->default(0);
            $table->decimal('split_15_amount', 12, 2)->default(0);
            $table->date('split_85_paid_on')->nullable();
            $table->date('split_15_paid_on')->nullable();
            $table->string('split_85_transaction')->nullable();
            $table->string('split_15_transaction')->nullable();
            
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_claims');
    }
};
