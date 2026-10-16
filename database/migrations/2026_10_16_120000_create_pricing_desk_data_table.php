<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row store for the Sales Pricing Desk tool's data (price lists,
     * discount rules, calculator factors, reference tables). Kept as one JSON
     * blob rather than normalised tables because the tool itself already
     * reads/writes this exact shape client-side — the backend's only job is
     * to be where that shape now lives, instead of baked into a static HTML
     * file that had to be hand-edited and re-uploaded to publish a change.
     */
    public function up(): void
    {
        Schema::create('pricing_desk_data', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->string('version')->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_desk_data');
    }
};
