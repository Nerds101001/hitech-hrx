<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * announcements.content holds two incompatible kinds of data: plain text
 * typed into a textarea (both manual creation forms), and real
 * server-rendered HTML (the auto-published new-joiner welcome post — see
 * HrNotificationService::publishWelcomeAnnouncement()). Every employee-facing
 * feed escaped everything, so welcome posts showed raw <div>/<img>/<strong>
 * tags as literal text.
 *
 * An explicit flag, not a "does it look like HTML" heuristic — the manual
 * forms accept free-text HR/admin input with no rich-text editor, so
 * rendering anything that merely contains a tag as raw HTML would be a
 * stored-XSS hole the moment someone pastes something tag-shaped. Only code
 * that generates the HTML itself (never rendering unsanitised user input)
 * may set this true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_html')->default(false)->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('is_html');
        });
    }
};
