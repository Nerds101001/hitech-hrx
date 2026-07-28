<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part-day holidays.
 *
 * The staff holiday list has three real kinds of day, but the table could only
 * express one:
 *   full  — whole day off (Diwali)
 *   half  — half day off  (work half, rest of the day off)
 *   hours — a few hours off (Raksha Bandhan "morning timing 11:30", Vaisakhi
 *           "4 PM off", Karwa Chauth "female staff 2 PM off")
 *
 * A 'full' holiday means no attendance is expected. 'half' and 'hours' are
 * still WORKING days — they simply reduce the hours an employee must put in
 * before the day counts as fully present, so a 2-hour concession turns an
 * 8-hour day into a 6-hour one instead of wiping the day out.
 *
 * `applies_to_gender` covers the female-staff-only concessions in the sheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->enum('holiday_type', ['full', 'half', 'hours'])
                  ->default('full')->after('date');
            $table->decimal('hours_off', 4, 2)->nullable()->after('holiday_type');
            $table->enum('session', ['morning', 'afternoon'])->nullable()->after('hours_off');
            $table->enum('applies_to_gender', ['all', 'male', 'female'])
                  ->default('all')->after('session');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['holiday_type', 'hours_off', 'session', 'applies_to_gender']);
        });
    }
};
