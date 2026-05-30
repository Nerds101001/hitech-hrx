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
        Schema::create('travel_claim_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_claim_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('mode')->nullable(); // Cash, Cheque
            $table->string('cheque_number')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_claim_advances');
    }
};
