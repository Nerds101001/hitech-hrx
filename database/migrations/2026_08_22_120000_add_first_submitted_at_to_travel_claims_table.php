<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a claim was first submitted, so the 10% late penalty can be judged
 * against that moment instead of against whenever it was last saved.
 *
 * Without it there was nothing on the row recording the original filing, so a
 * claim sent back for a correction and resubmitted after the 7th was treated
 * as a late claim — as was one merely reopened, since the background autosave
 * recomputed the flag too.
 */
return new class extends Migration
{
    /*
     * Guarded on both counts. travel_claims is created by a tenant migration
     * that the default set does not run, so an unguarded Schema::table() here
     * fails on any database built from the app migrations alone — which is how
     * the Telephony suite provisions its sqlite schema. The column check keeps
     * a re-run harmless on production.
     */
    public function up(): void
    {
        if (!Schema::hasTable('travel_claims') || Schema::hasColumn('travel_claims', 'first_submitted_at')) {
            return;
        }

        Schema::table('travel_claims', function (Blueprint $table) {
            $table->timestamp('first_submitted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('travel_claims') || !Schema::hasColumn('travel_claims', 'first_submitted_at')) {
            return;
        }

        Schema::table('travel_claims', function (Blueprint $table) {
            $table->dropColumn('first_submitted_at');
        });
    }
};
