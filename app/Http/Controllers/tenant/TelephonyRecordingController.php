<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyRecording;
use App\Services\Telephony\CentroVisionClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plays a recording without ever handing the browser a gateway URL.
 *
 * HRX checks permission, asks the vendor for a <=300s signed URL, then streams
 * the bytes through this endpoint. The audio never lands on HRX disk and the
 * signed URL never leaves the server, so a shared link or a devtools copy gets
 * a listener nothing.
 */
class TelephonyRecordingController extends Controller
{
  public function __construct(private CentroVisionClient $vendor)
  {
  }

  public function stream(Request $request, string $recordingUid): StreamedResponse
  {
    $user = $request->user();

    // No tenant filter here, for the same reason as TelephonyCall::visibleTo():
    // every user carries tenant_id NULL on this install, so `tenant_id = NULL`
    // would 404 every recording. Authorisation is mayPlay() below, which is
    // where it belongs anyway.
    $recording = TelephonyRecording::where('recording_uid', $recordingUid)
      ->firstOrFail();

    $call = TelephonyCall::where('call_uid', $recording->call_uid)->first();

    if (!$call || !$this->mayPlay($user, $call)) {
      $this->log($request, $recording, 'denied', 'Outside the caller\'s permitted scope');

      abort(403, 'You do not have permission to play this recording.');
    }

    if ($recording->isExpired()) {
      $this->log($request, $recording, 'denied', 'Past the vendor retention date');

      abort(410, 'This recording is past its retention period and no longer exists.');
    }

    // Two ways to reach the audio. A URL the vendor pushed is used first when
    // one is present and unexpired, because a vendor that pushes URLs is
    // precisely a vendor with no signed-url endpoint to ask -- and
    // recordingSignedUrl() throws on failure, so asking first would mean an
    // exception, a logged error and a wasted round trip on every single play.
    //
    // Gateways that do serve signed URLs push nothing, fall through, and keep
    // the original behaviour: a link that expires in minutes and is therefore
    // worth little if it ever leaks.
    //
    // Either route streams the bytes below rather than redirecting the
    // browser, so the URL never leaves this server and playback stays
    // permission-checked and logged whichever one produced it.
    $signed = $recording->hasUsableUrl()
      ? ['url' => $recording->recording_url]
      : $this->vendor->recordingSignedUrl($recordingUid);

    if (empty($signed['url'])) {
      abort(502, 'The gateway did not return a playable link for this recording.');
    }

    $this->log($request, $recording, 'play');

    // Forward the browser's Range header so seeking in the player works.
    // The signed URL is served by the same on-prem gateway CentroVisionClient
    // talks to, so it needs the same TLS setting. Without this a self-signed
    // box fails the handshake here even though the signed-URL call that
    // produced this link succeeded.
    $upstream = Http::withHeaders(array_filter([
      'Range' => $request->header('Range'),
    ]))->withOptions([
      'stream' => true,
      'verify' => config('telephony.vendor.verify_tls'),
    ])->get($signed['url']);

    $contentType = $signed['content_type'] ?? $this->mimeFor($recording->format);
    $status = $request->hasHeader('Range') ? 206 : 200;

    return response()->stream(
      function () use ($upstream) {
        $body = $upstream->toPsrResponse()->getBody();
        while (!$body->eof()) {
          echo $body->read(65536);
          flush();
        }
      },
      $status,
      array_filter([
        'Content-Type' => $contentType,
        'Accept-Ranges' => 'bytes',
        'Content-Length' => $upstream->header('Content-Length') ?: null,
        'Content-Range' => $upstream->header('Content-Range') ?: null,
        'Cache-Control' => 'private, no-store',
        'Content-Disposition' => 'inline; filename="' . $recording->call_uid . '.' . ($recording->format ?: 'wav') . '"',
      ])
    );
  }

  private function mayPlay($user, TelephonyCall $call): bool
  {
    if ($call->user_id === $user->id) {
      return true;
    }

    return $user->hasAnyRole(config('telephony.recording.supervisor_roles'))
      && TelephonyCall::query()->visibleTo($user)->whereKey($call->id)->exists();
  }

  private function log(Request $request, TelephonyRecording $recording, string $action, ?string $reason = null): void
  {
    DB::table('telephony_recording_access_logs')->insert([
      'tenant_id' => $request->user()->tenant_id ?: config('telephony.inbound.default_tenant_id'),
      'user_id' => $request->user()->id,
      'recording_uid' => $recording->recording_uid,
      'call_uid' => $recording->call_uid,
      'action' => $action,
      'reason' => $reason,
      'ip' => $request->ip(),
      'user_agent' => substr((string) $request->userAgent(), 0, 512),
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  private function mimeFor(?string $format): string
  {
    return match ($format) {
      'mp3' => 'audio/mpeg',
      'ogg' => 'audio/ogg',
      'gsm' => 'audio/x-gsm',
      default => 'audio/wav',
    };
  }
}
