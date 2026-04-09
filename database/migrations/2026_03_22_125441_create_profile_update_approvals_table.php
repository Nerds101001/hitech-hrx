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
        Schema::create('profile_update_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // profile, bank, document
            $table->json('requested_data');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('actioned_by_id')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_update_approvals');
    }
};
