<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_visits', function (Blueprint $table) {
            $table->id();
            $table->enum('visit_type', ['client_visit', 'product_trial', 'order_collection', 'service_call'])->default('client_visit');
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('sales_clients')->onDelete('cascade');
            $table->unsignedBigInteger('salesperson_id');
            $table->foreign('salesperson_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('cc_user_id');
            $table->foreign('cc_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->dateTime('scheduled_at');
            $table->text('notes')->nullable();
            $table->string('proof_photo')->nullable();
            $table->decimal('completed_lat', 10, 7)->nullable();
            $table->decimal('completed_lng', 10, 7)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('sales_visits'); }
};
