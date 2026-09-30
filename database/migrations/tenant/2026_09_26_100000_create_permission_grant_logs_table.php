<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who granted or removed a person's special (direct) permission, and when.
 * Spatie's model_has_permissions pivot records neither, so "Added by X on
 * date" on the User Special Permissions page reads from here. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_grant_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('permission');
            $table->string('action', 10); // granted | revoked
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_grant_logs');
    }
};
