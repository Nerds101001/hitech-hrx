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
        Schema::create('hr_policy_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('hr_policy_id')->index();
            $table->timestamp('acknowledged_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('signature_data')->nullable(); // Stores details like 'Signed by: Name'
            $table->string('receipt_path')->nullable(); // Path to the generated signed PDF
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('hr_policy_id')->references('id')->on('hr_policies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_policy_acknowledgments');
    }
};
