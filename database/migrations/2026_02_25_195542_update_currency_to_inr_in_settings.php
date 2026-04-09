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
        \Illuminate\Support\Facades\DB::table('settings')->update([
            'currency' => 'INR',
            'currency_symbol' => '₹'
        ]);

        if (\Illuminate\Support\Facades\Schema::hasTable('sa_settings')) {
            \Illuminate\Support\Facades\DB::table('sa_settings')->update([
                'currency' => 'INR',
                'currency_symbol' => '₹'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->update([
            'currency' => 'USD',
            'currency_symbol' => '$'
        ]);

        if (\Illuminate\Support\Facades\Schema::hasTable('sa_settings')) {
            \Illuminate\Support\Facades\DB::table('sa_settings')->update([
                'currency' => 'USD',
                'currency_symbol' => '$'
            ]);
        }
    }
};
