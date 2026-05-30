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
        Schema::create('travel_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_claim_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('party_visited')->nullable();
            
            $table->string('mode_of_travel')->nullable(); // Bike, Car, Bus, Train, Flight
            $table->decimal('start_meter', 10, 2)->nullable();
            $table->decimal('end_meter', 10, 2)->nullable();
            $table->decimal('distance_km', 8, 2)->default(0);
            
            $table->string('photo_path')->nullable(); // Odometer photo proof
            $table->boolean('penalty_applied')->default(false); // If photo missing, 30% reduction applied
            
            $table->decimal('conveyance_amount', 10, 2)->default(0);
            $table->decimal('food_allowance', 10, 2)->default(0);
            $table->decimal('lodging_amount', 10, 2)->default(0);
            $table->decimal('courier_amount', 10, 2)->default(0);
            $table->decimal('other_amount', 10, 2)->default(0); // Any other miscellaneous amount
            
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_claim_items');
    }
};
