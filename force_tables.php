<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

try {
    if (!Schema::hasTable('travel_claims')) {
        Schema::create('travel_claims', function (Blueprint \) {
            \->id();
            \->foreignId('user_id')->constrained()->onDelete('cascade');
            \->string('claim_month');
            \->string('company')->default('Hi Tech International');
            \->decimal('sales_collection', 15, 2)->default(0);
            \->string('status')->default('draft');
            \->decimal('total_amount', 12, 2)->default(0);
            \->decimal('total_advances', 12, 2)->default(0);
            \->decimal('net_payable', 12, 2)->default(0);
            \->boolean('late_penalty_applied')->default(false);
            \->string('bank_account_name')->nullable();
            \->string('bank_account_no')->nullable();
            \->string('bank_ifsc')->nullable();
            \->foreignId('verifier_id')->nullable()->constrained('users')->onDelete('set null');
            \->timestamp('verified_at')->nullable();
            \->foreignId('payer_id')->nullable()->constrained('users')->onDelete('set null');
            \->timestamp('paid_at')->nullable();
            \->decimal('split_85_amount', 12, 2)->default(0);
            \->decimal('split_15_amount', 12, 2)->default(0);
            \->date('split_85_paid_on')->nullable();
            \->date('split_15_paid_on')->nullable();
            \->string('split_85_transaction')->nullable();
            \->string('split_15_transaction')->nullable();
            \->text('remarks')->nullable();
            \->timestamps();
        });
        echo "Created travel_claims.\n";
    } else {
        echo "travel_claims already exists.\n";
    }

    if (!Schema::hasTable('travel_claim_items')) {
        Schema::create('travel_claim_items', function (Blueprint \) {
            \->id();
            \->foreignId('travel_claim_id')->constrained()->onDelete('cascade');
            \->date('date');
            \->string('location');
            \->string('expense_type');
            \->decimal('amount', 10, 2);
            \->string('bill_path')->nullable();
            \->text('remarks')->nullable();
            \->timestamps();
        });
        echo "Created travel_claim_items.\n";
    }

    if (!Schema::hasTable('travel_claim_advances')) {
        Schema::create('travel_claim_advances', function (Blueprint \) {
            \->id();
            \->foreignId('travel_claim_id')->constrained()->onDelete('cascade');
            \->decimal('amount', 10, 2);
            \->string('advance_type')->default('cash');
            \->date('advance_date')->nullable();
            \->text('remarks')->nullable();
            \->timestamps();
        });
        echo "Created travel_claim_advances.\n";
    }
} catch (\Exception \) {
    echo "Error: " . \->getMessage() . "\n";
}
