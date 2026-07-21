<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_claim_items', 'is_flagged')) {
                $table->boolean('is_flagged')->default(false)->after('penalty_applied');
            }
            if (!Schema::hasColumn('travel_claim_items', 'flag_reason')) {
                $table->string('flag_reason', 500)->nullable()->after('is_flagged');
            }
        });
    }

    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            $table->dropColumn(['is_flagged', 'flag_reason']);
        });
    }
};
