<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named, admin-created 90-day drive with its own start/end date and its own invited
 * participants — e.g. "Q3 North Zone Push" running 1 Jul to 29 Sep, alongside a completely
 * separate "Export Team H2 Drive" running 15 Aug to 13 Nov. Each invited person still gets
 * their own ninety_day_rounds row (round_id keeps meaning exactly what it always has:
 * checkins, tasks, the dashboard); ninety_day_rounds.program_id just says which program (if
 * any) that particular round belongs to. A round with no program is the old behaviour —
 * started on its own, the Monday after the person's last one closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ninety_day_programs')) {
            Schema::create('ninety_day_programs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('name');
                $t->date('starts_on');
                $t->date('ends_on');
                $t->unsignedBigInteger('created_by_id');
                $t->timestamps();
            });
        }

        Schema::table('ninety_day_rounds', function (Blueprint $t) {
            if (!Schema::hasColumn('ninety_day_rounds', 'program_id')) {
                $t->unsignedBigInteger('program_id')->nullable()->index()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ninety_day_rounds', function (Blueprint $t) {
            $t->dropColumn('program_id');
        });
        Schema::dropIfExists('ninety_day_programs');
    }
};
