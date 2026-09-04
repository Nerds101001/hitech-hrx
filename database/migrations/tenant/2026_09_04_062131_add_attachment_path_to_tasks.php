<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // The reference photo/document attached when the task itself was
            // created (e.g. "this is what the completed shelf should look
            // like"). Completion/objection evidence goes on task_updates.file_url
            // instead, which already existed but was never wired up.
            $table->text('attachment_path')->nullable()->after('objection_notes');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
