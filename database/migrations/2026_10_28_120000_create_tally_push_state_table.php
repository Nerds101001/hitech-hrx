<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Tally company code (hitech, kif, ...), tracking how far hrx:tally-push's AUTO mode (no
 * --date/--from/--to -- i.e. the scheduled task's own invocation) has actually gotten, so a day that failed
 * cleanly (company not loaded, Tally unreachable, ...) gets picked back up by the NEXT scheduled run instead
 * of staying a silent gap until someone notices and backfills it by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_push_state', function (Blueprint $table) {
            $table->string('company', 20)->primary();
            // The last calendar day this company's auto push got all the way through. Null means "never
            // succeeded yet" -- the next auto run starts from today, same as the old behaviour.
            $table->date('last_success_date')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('last_failure_message')->nullable();
            // Last time a failure email actually went out for this company's streak -- a 24h cooldown so a
            // company left unloaded for a week sends one email, then a daily reminder, not one every 2 hours.
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_push_state');
    }
};
