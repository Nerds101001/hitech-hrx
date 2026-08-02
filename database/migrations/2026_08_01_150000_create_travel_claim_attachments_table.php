<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each monthly bill category used to be a single varchar path on travel_claims,
 * so a month's bills had to be merged into one file before uploading. This table
 * holds many files per category instead.
 *
 * Files are uploaded before the claim exists (the user is still filling the
 * form), so rows are first written against `upload_token` with a null
 * travel_claim_id and adopted by the claim when it is saved. The old single-path
 * columns are left in place — historical claims still read from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_claim_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_claim_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('upload_token', 64)->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category', 64);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            // Adopting a token's files on save, and listing a claim's files per
            // category, are the only two ways this table is ever read.
            $table->index(['upload_token', 'travel_claim_id'], 'tca_token_claim_idx');
            $table->index(['travel_claim_id', 'category'], 'tca_claim_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_claim_attachments');
    }
};
