<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Places a call through the Smart Agent dialer.
 *
 * The dialer answers every outcome with HTTP 200 and a plain-text body, so the
 * body string is the only signal there is — never the status code. Its five
 * documented replies are mapped here into something a dashboard can show.
 */
class ClickToCallService
{
    /** Dialer replies, mapped to how the UI should present them. */
    private const OUTCOMES = [
        'success to dial' => [
            'result'  => 'success',
            'message' => 'Calling now — pick up your agent phone.',
        ],
        'agent busy' => [
            'result'  => 'busy',
            'message' => 'You are already on a call or in wrap-up. Finish that first, then try again.',
        ],
        'agent not login or exist' => [
            'result'  => 'not_logged_in',
            'message' => 'You are not logged into Smart Agent. Sign in there first, then try again.',
        ],
        'agent not in manual or progressive mode' => [
            'result'  => 'wrong_mode',
            'message' => 'Your campaign is not in manual or progressive mode, so it will not accept a click-to-call.',
        ],
    ];

    /** The dialer identity for this login, or null if they may not dial. */
    public function agentFor(User $user): ?array
    {
        if (!config('access.click_to_call.enabled')) {
            return null;
        }

        $agents = array_change_key_case((array) config('access.click_to_call.agents', []), CASE_LOWER);

        return $agents[strtolower((string) $user->email)] ?? null;
    }

    public function canCall(User $user): bool
    {
        return $this->agentFor($user) !== null;
    }

    /**
     * Keep digits only. The dialer wants a bare number, but people paste
     * "+91 70874-32763" out of a CRM.
     */
    public function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Indian numbers arrive with a country code as often as without; the
        // dialer expects the local 10-digit form.
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * @return array{result:string, message:string, raw:string}
     */
    public function dial(User $user, string $phone): array
    {
        $agent = $this->agentFor($user);

        if (!$agent) {
            return ['result' => 'forbidden', 'message' => 'Click to call is not enabled for your account.', 'raw' => ''];
        }

        $query = [
            'ACTION'    => 'DIAL',
            'campaign'  => $agent['campaign'],
            'agent'     => $agent['agent'],
            'line'      => 0,
            'phone'     => $phone,
            'client_id' => $agent['client_id'],
        ];

        // Every dial rings a real person and costs a call leg, so each attempt
        // is recorded against the user who made it.
        Log::info("CLICK_TO_CALL: user {$user->id} ({$user->email}) dialling {$phone} as agent {$agent['agent']}");

        try {
            $response = Http::timeout((int) config('access.click_to_call.timeout', 10))
                ->get(rtrim((string) config('access.click_to_call.endpoint'), '/') . '/', $query);
        } catch (\Throwable $e) {
            Log::error("CLICK_TO_CALL_UNREACHABLE: " . $e->getMessage());

            return [
                'result'  => 'unreachable',
                'message' => 'Could not reach the dialer. It may be down or blocking this server.',
                'raw'     => '',
            ];
        }

        $raw = trim(strip_tags($response->body()));
        Log::info("CLICK_TO_CALL_RESPONSE [{$user->id}]: {$raw}");

        foreach (self::OUTCOMES as $needle => $outcome) {
            if (str_contains(strtolower($raw), $needle)) {
                return $outcome + ['raw' => $raw];
            }
        }

        // Documented catch-all is a bare "Error"; anything unrecognised lands
        // here too rather than being reported as a placed call.
        return [
            'result'  => 'error',
            'message' => 'The dialer refused the call' . ($raw !== '' ? ": {$raw}" : '.'),
            'raw'     => $raw,
        ];
    }
}
