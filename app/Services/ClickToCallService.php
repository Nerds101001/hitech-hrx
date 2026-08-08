<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the CentroVision agent API (ACTION=DIAL/MODE/DISPOSE/HANGUP/LIST).
 *
 * Two things about this API shape everything below:
 *
 *  - Every outcome comes back as HTTP 200 with a plain-text body, so the body
 *    string is the only signal there is. Never trust the status code.
 *  - It is plain HTTP behind a source-address allow-list, so the browser must
 *    never call it directly (HRX is HTTPS; mixed content is blocked) and it
 *    only answers from the production server.
 */
class ClickToCallService
{
    /** Replies that mean "this worked", per action. */
    private const SUCCESS = [
        'DIAL'    => 'success to dial',
        'MODE'    => 'agent mode changed',
        'DISPOSE' => 'call disposed',
        'HANGUP'  => 'call hangup',
        'HOLD'    => 'call hold',
        'UNHOLD'  => 'call unhold',
        'MUTE'    => 'call mute',
        'UNMUTE'  => 'call unmute',
        'LOGOUT'  => 'agent logout',
    ];

    /** Refusals worth explaining in the agent's own words. */
    private const REFUSALS = [
        'agent not in manual or progressive mode' => ['wrong_mode', 'Your campaign is not in manual or progressive mode.'],
        'agent not login or exist'                => ['not_logged_in', 'You are not logged into Smart Agent. Sign in there first, then try again.'],
        'agent busy'                              => ['busy', 'You are already on a call or in wrap-up. Finish that first, then try again.'],
        'same mode not allowed'                   => ['same_mode', 'You are already in that mode.'],
        'agent not idle'                          => ['not_idle', 'You are on a call, so you cannot log out yet.'],
        'agent not on call'                       => ['not_on_call', 'There is no active call.'],
        'agent not on hold'                       => ['not_on_hold', 'That call is not on hold.'],
        'agent not on mute'                       => ['not_on_mute', 'That call is not muted.'],
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
     * "+91 78890-41267" out of a CRM.
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

    // ---------------------------------------------------------------- actions

    /**
     * Dial, fixing the agent's mode if that is the only thing in the way.
     *
     * A campaign left in auto mode refuses click-to-call outright. Rather than
     * telling the agent to go and change it in another application, switch to
     * manual and dial again — one retry only, so a persistently wrong mode
     * surfaces instead of looping.
     */
    public function dial(User $user, string $phone, bool $autoFixMode = true): array
    {
        $result = $this->send($user, 'DIAL', ['line' => 0, 'phone' => $phone]);

        if ($result['result'] !== 'wrong_mode' || !$autoFixMode) {
            return $result;
        }

        Log::info("CLICK_TO_CALL_MODE_FIX: user {$user->id} is not in a dialable mode; switching to manual");

        $switch = $this->switchToManual($user);

        if ($switch['result'] !== 'success') {
            // Report why the mode could not be changed, not the original
            // refusal — that is the part the agent can act on.
            return $switch + ['mode_fix_attempted' => true];
        }

        $retry = $this->send($user, 'DIAL', ['line' => 0, 'phone' => $phone]);
        $retry['mode_fix_attempted'] = true;

        if ($retry['result'] === 'success') {
            $retry['message'] = 'Switched you to manual mode and placed the call — pick up your agent phone.';
        }

        return $retry;
    }

    /** Move the agent into a mode that accepts click-to-call. */
    public function switchToManual(User $user): array
    {
        // Ask the dialer which ids this campaign actually uses rather than
        // trusting the document's example numbering.
        $modes = $this->modeList($user);
        $id    = null;
        $name  = 'Manual';

        foreach ($modes as $modeId => $modeName) {
            if (strcasecmp($modeName, 'Manual') === 0) {
                $id   = $modeId;
                $name = $modeName;
                break;
            }
        }

        if ($id === null) {
            // Documented default: 1=Auto, 2=Manual, 3=Callback, 4=Break, 5=Progressive.
            $id = 2;
        }

        return $this->send($user, 'MODE', ['mode' => $id, 'modetext' => $name]);
    }

    public function hangup(User $user): array
    {
        return $this->send($user, 'HANGUP', ['line' => 0]);
    }

    public function dispose(User $user, string $disposition, ?string $subDisposition = null): array
    {
        $params = ['disposition' => $disposition];

        if ($subDisposition !== null && $subDisposition !== '') {
            $params['sub_disposition'] = $subDisposition;
        }

        return $this->send($user, 'DISPOSE', $params);
    }

    public function lineStatus(User $user): array
    {
        return $this->send($user, 'LINESTATUS', []);
    }

    // ------------------------------------------------------------------ lists

    /** @return array<int|string,string> mode id => mode name */
    public function modeList(User $user): array
    {
        $raw = $this->send($user, 'LIST', ['list' => 'mode'])['raw'];

        $modes = [];
        foreach ($this->splitList($raw) as $entry) {
            // Entries look like "2=Manual".
            if (str_contains($entry, '=')) {
                [$id, $label] = explode('=', $entry, 2);
                $modes[trim($id)] = trim($label);
            }
        }

        return $modes;
    }

    /** @return string[] */
    public function dispositions(User $user): array
    {
        return $this->splitList($this->send($user, 'LIST', ['list' => 'disposition'])['raw']);
    }

    /** @return string[] */
    public function subDispositions(User $user, string $disposition): array
    {
        return $this->splitList(
            $this->send($user, 'LIST', ['list' => 'sub_disposition', 'disposition' => $disposition])['raw']
        );
    }

    /** Lists arrive pipe-delimited and sometimes end with a stray full stop. */
    private function splitList(string $raw): array
    {
        // A refusal is not a list.
        foreach (array_keys(self::REFUSALS) as $refusal) {
            if (str_contains(strtolower($raw), $refusal)) {
                return [];
            }
        }

        $items = array_map(fn ($v) => trim(rtrim(trim($v), '.')), explode('|', $raw));

        return array_values(array_filter($items, fn ($v) => $v !== ''));
    }

    // --------------------------------------------------------------- transport

    /**
     * @return array{result:string, message:string, raw:string}
     */
    private function send(User $user, string $action, array $params): array
    {
        $agent = $this->agentFor($user);

        if (!$agent) {
            return ['result' => 'forbidden', 'message' => 'Click to call is not enabled for your account.', 'raw' => ''];
        }

        $query = array_merge([
            'ACTION'   => $action,
            'campaign' => $agent['campaign'],
            'agent'    => $agent['agent'],
        ], $params, ['client_id' => $agent['client_id']]);

        // Each dial rings a real person and costs a call leg, so every attempt
        // is recorded against whoever made it.
        Log::info("CLICK_TO_CALL {$action}: user {$user->id} ({$user->email}) as agent {$agent['agent']} " . json_encode($params));

        try {
            $response = Http::timeout((int) config('access.click_to_call.timeout', 10))
                ->get(rtrim((string) config('access.click_to_call.endpoint'), '/') . '/', $query);
        } catch (\Throwable $e) {
            Log::error("CLICK_TO_CALL_UNREACHABLE [{$action}]: " . $e->getMessage());

            return [
                'result'  => 'unreachable',
                'message' => 'Could not reach the dialer. It may be down or blocking this server.',
                'raw'     => '',
            ];
        }

        $raw   = trim(strip_tags($response->body()));
        $lower = strtolower($raw);
        Log::info("CLICK_TO_CALL_RESPONSE [{$action}][{$user->id}]: {$raw}");

        if (isset(self::SUCCESS[$action]) && str_contains($lower, self::SUCCESS[$action])) {
            return ['result' => 'success', 'message' => $this->successMessage($action), 'raw' => $raw];
        }

        foreach (self::REFUSALS as $needle => [$code, $message]) {
            if (str_contains($lower, $needle)) {
                return ['result' => $code, 'message' => $message, 'raw' => $raw];
            }
        }

        // LIST and LINESTATUS have no success keyword — a non-refusal body is
        // the payload itself.
        if (in_array($action, ['LIST', 'LINESTATUS'], true) && $raw !== '') {
            return ['result' => 'success', 'message' => $raw, 'raw' => $raw];
        }

        // Documented catch-all is a bare "Error"; anything unrecognised lands
        // here too rather than being reported as though it worked.
        return [
            'result'  => 'error',
            'message' => 'The dialer refused the request' . ($raw !== '' ? ": {$raw}" : '.'),
            'raw'     => $raw,
        ];
    }

    private function successMessage(string $action): string
    {
        return match ($action) {
            'DIAL'    => 'Calling now — pick up your agent phone.',
            'MODE'    => 'Mode changed.',
            'DISPOSE' => 'Disposition saved.',
            'HANGUP'  => 'Call ended.',
            default   => 'Done.',
        };
    }
}
