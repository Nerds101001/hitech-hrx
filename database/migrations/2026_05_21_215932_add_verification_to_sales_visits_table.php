<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending')->after('status');
            $table->text('verification_notes')->nullable()->after('verification_status');
        });
    }
    public function down(): void {
        Schema::table('sales_visits', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verification_notes']);
        });
    }
};
