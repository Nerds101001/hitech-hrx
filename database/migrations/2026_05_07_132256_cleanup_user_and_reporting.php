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
        // Delete the specified user
        \App\Models\User::where('email', 'abc1010012@gmail.com')->delete();

        // Remove reporting for mukul
        \App\Models\User::where('email', 'mukul@rustx.com')->update([
            'reporting_to_id' => null
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for data cleanup
    }
};
