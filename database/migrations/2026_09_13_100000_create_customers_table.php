<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The canonical customer registry. Sales Report (`sales_pipelines`) has
 * always stored the customer as a free-text `party_name`, which is why the
 * same company shows up under several spellings ("RS Enterprises" / "RS
 * ENTERPRISES") with no way to treat them as one entity. This table gives
 * every distinct company exactly one row; `sales_pipelines.customer_id`
 * (added in the next migration) links to it. `party_name` itself is left
 * untouched — this is purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name');
            // App\Support\NameMatcher::normalize() output — the real dedup key.
            $table->string('normalized_name')->unique();

            // Setu (Tally) verification state. 'none' until a match is found or
            // ruled out; 'suggested' when an exact-normalized match exists but a
            // human hasn't confirmed it; 'verified' once a human has.
            $table->enum('setu_match_status', ['none', 'suggested', 'verified'])->default('none');
            $table->string('setu_matched_name')->nullable();
            $table->timestamp('setu_verified_at')->nullable();
            $table->foreignId('setu_verified_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->index('setu_match_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
