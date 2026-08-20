<?php

namespace App\Http\Controllers\tenant\Telephony;

use App\Http\Controllers\Controller;
use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyCredential;
use App\Models\Telephony\TelephonyWebhookDelivery;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The connection screen: credentials, and proof the vendor is actually
 * sending.
 *
 * Everything here used to require SSH access and a .env edit. A credential
 * that can only be rotated by someone with a terminal is a credential that
 * never gets rotated, and "are they pushing yet?" was a question only
 * answerable by reading a log file.
 */
class TelephonyConnectionController extends Controller
{
  public function index(Request $request): View
  {
    $credentials = TelephonyCredential::with('createdBy:id,first_name,last_name')
      ->orderByDesc('is_active')
      ->orderByDesc('created_at')
      ->get();

    // A secret is readable exactly once, in the response to the request that
    // created it. It is encrypted at rest and never rendered again, so this is
    // flashed to the session rather than fetched.
    $justIssued = $request->session()->get('telephony_new_credential');

    return view('tenant.telephony.admin.connection', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'credentials' => $credentials,
      'justIssued' => $justIssued,
      'endpoint' => url('/api/telephony/v1/events'),
      'healthUrl' => url('/api/telephony/v1/health'),
      'configFallback' => $this->configFallback(),
      'stats' => $this->stats(),
    ]);
  }

  /**
   * Is anything still relying on the .env credentials?
   *
   * Worth surfacing: if a key is set in config AND in the table, the table
   * wins, and someone will eventually change the wrong one and wonder why
   * nothing happened.
   */
  private function configFallback(): array
  {
    $key = (string) config('telephony.inbound.api_key');

    return [
      'in_use' => $key !== '' && TelephonyCredential::where('is_active', true)->doesntExist(),
      'configured' => $key !== '',
      'allowed_ips' => config('telephony.inbound.allowed_ips', []),
    ];
  }

  /**
   * How much has actually arrived.
   *
   * Deliberately counts DELIVERIES rather than calls: a vendor can be pushing
   * perfectly while every payload is rejected for a bad signature, and a
   * screen that only counted successful calls would show zero and give no clue
   * why.
   */
  private function stats(): array
  {
    $since = now()->subDays(29)->startOfDay();

    $daily = TelephonyWebhookDelivery::where('received_at', '>=', $since)
      ->selectRaw('DATE(received_at) as day, status, COUNT(*) as n')
      ->groupBy('day', 'status')
      ->get()
      ->groupBy('day');

    $series = [];
    for ($d = $since->copy(); $d->lte(now()); $d->addDay()) {
      $key = $d->toDateString();
      $rows = $daily->get($key, collect());

      $series[] = [
        'day' => $d->copy(),
        'accepted' => (int) $rows->whereIn('status', ['queued', 'processed'])->sum('n'),
        'rejected' => (int) $rows->whereIn('status', ['rejected', 'failed'])->sum('n'),
      ];
    }

    $byEndpoint = TelephonyWebhookDelivery::where('received_at', '>=', $since)
      ->selectRaw('endpoint, COUNT(*) as n, MAX(received_at) as last_seen')
      ->groupBy('endpoint')
      ->orderByDesc('n')
      ->get();

    $byEvent = TelephonyWebhookDelivery::where('received_at', '>=', $since)
      ->whereNotNull('event_type')
      ->selectRaw('event_type, COUNT(*) as n')
      ->groupBy('event_type')
      ->orderByDesc('n')
      ->limit(15)
      ->get();

    $lastAccepted = TelephonyWebhookDelivery::where('status', '!=', 'rejected')->max('received_at');

    return [
      'total' => TelephonyWebhookDelivery::count(),
      'today' => TelephonyWebhookDelivery::whereDate('received_at', today())->count(),
      'accepted_30d' => collect($series)->sum('accepted'),
      'rejected_30d' => collect($series)->sum('rejected'),
      'calls' => TelephonyCall::count(),
      'series' => $series,
      'by_endpoint' => $byEndpoint,
      'by_event' => $byEvent,
      'last_accepted' => $lastAccepted ? Carbon::parse($lastAccepted) : null,
    ];
  }

  // ──────────────────────────────────────────────────────────── mutations

  public function store(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'label' => ['required', 'string', 'max:120'],
      'allowed_ips' => ['nullable', 'string', 'max:500'],
      'notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $secret = TelephonyCredential::newSecret();

    $credential = TelephonyCredential::create([
      'tenant_id' => config('telephony.inbound.default_tenant_id'),
      'label' => $data['label'],
      'api_key' => TelephonyCredential::newApiKey(),
      'secret' => $secret,
      'allowed_ips' => $data['allowed_ips'] ?: null,
      'notes' => $data['notes'] ?? null,
      'is_active' => true,
      'created_by_id' => $request->user()->id,
    ]);

    // The only moment the secret is readable. Flashed, not stored.
    return back()->with('telephony_new_credential', [
      'label' => $credential->label,
      'api_key' => $credential->api_key,
      'secret' => $secret,
      'rotated' => false,
    ]);
  }

  public function rotate(Request $request, TelephonyCredential $credential): RedirectResponse
  {
    $days = (int) $request->validate([
      'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
    ])['grace_days'];

    $secret = $credential->rotateSecret($days);
    $this->forget($credential);

    return back()->with('telephony_new_credential', [
      'label' => $credential->label,
      'api_key' => $credential->api_key,
      'secret' => $secret,
      'rotated' => true,
      'grace_days' => $days,
    ]);
  }

  public function update(Request $request, TelephonyCredential $credential): RedirectResponse
  {
    $data = $request->validate([
      'label' => ['required', 'string', 'max:120'],
      'allowed_ips' => ['nullable', 'string', 'max:500'],
      'notes' => ['nullable', 'string', 'max:1000'],
      'is_active' => ['nullable', 'boolean'],
    ]);

    $credential->update([
      'label' => $data['label'],
      'allowed_ips' => $data['allowed_ips'] ?: null,
      'notes' => $data['notes'] ?? null,
      'is_active' => $request->boolean('is_active'),
    ]);

    $this->forget($credential);

    return back()->with('success', 'Credential updated.');
  }

  public function destroy(TelephonyCredential $credential): RedirectResponse
  {
    $this->forget($credential);
    $credential->delete();

    return back()->with('success', 'Credential deleted. Any request still using it will be refused.');
  }

  /**
   * Drop the middleware's cached lookup so a change takes effect immediately
   * rather than up to a minute later.
   */
  private function forget(TelephonyCredential $credential): void
  {
    Cache::forget('telephony:cred:' . hash('sha256', (string) $credential->api_key));
  }
}
