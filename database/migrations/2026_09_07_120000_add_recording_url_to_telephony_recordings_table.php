<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a vendor push the audio location instead of exposing a signed-URL API.
 *
 * The original design was pull-only: HRX asked the gateway for a <=300s signed
 * URL at play time, so a link was never stored anywhere. That is still the
 * safer arrangement and is still preferred when a vendor can offer it -- but
 * several cannot, and refusing their recordings entirely helps nobody.
 *
 * `recording_url` is therefore a fallback, not a replacement. Playback still
 * goes through HRX, which streams the bytes itself: the URL is never handed to
 * the browser, so permission checks and the access log survive either way.
 *
 * `url_expires_at` matters because most vendors hand out time-limited links.
 * Without it HRX cannot tell a link that is merely stale from a gateway that is
 * down, and every expired recording becomes a support ticket about "the player
 * is broken".
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('telephony_recordings', function (Blueprint $table) {
      // text, not string: signed URLs carry tokens and routinely run past the
      // 255 a default string column allows.
      $table->text('recording_url')->nullable()->after('recording_type');
      $table->timestamp('url_expires_at')->nullable()->after('recording_url');
    });
  }

  public function down(): void
  {
    Schema::table('telephony_recordings', function (Blueprint $table) {
      $table->dropColumn(['recording_url', 'url_expires_at']);
    });
  }
};
