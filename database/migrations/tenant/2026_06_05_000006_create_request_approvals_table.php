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
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable'); // creates approvable_id, approvable_type
            $table->string('status'); // e.g. approved, rejected, pending
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // The person who acted
            $table->text('remarks')->nullable();
            
            $table->string('tenant_id', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
