<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcement targeting: HR/admin publish to the whole company or to any
 * combination of departments, locations (sites), roles and designations.
 * Managers publish only to their own team — audience_type 'team' resolves at
 * read time to the publisher's direct reports, so it stays correct as the
 * team changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('audience_type', ['global', 'targeted', 'team'])
                  ->default('global')->after('content');
            $table->json('target_department_ids')->nullable()->after('audience_type');
            $table->json('target_site_ids')->nullable()->after('target_department_ids');
            $table->json('target_role_names')->nullable()->after('target_site_ids');
            $table->json('target_designation_ids')->nullable()->after('target_role_names');
            $table->boolean('notify_email')->default(true)->after('target_designation_ids');
            $table->timestamp('published_at')->nullable()->after('notify_email');
            $table->unsignedInteger('recipients_count')->nullable()->after('published_at');
            $table->string('tenant_id', 191)->nullable()->after('recipients_count');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn([
                'audience_type',
                'target_department_ids',
                'target_site_ids',
                'target_role_names',
                'target_designation_ids',
                'notify_email',
                'published_at',
                'recipients_count',
                'tenant_id',
            ]);
        });
    }
};
