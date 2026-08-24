<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /**
     * The dialer is a third-party box on someone else's network. Its reply is
     * untrusted input: it reaches the log file, the JSON response and the
     * dashboard, so it is bounded and scrubbed before any of that. A healthy
     * reply is a short line like "Success to dial" or a pipe-delimited list.
     */
    private const MAX_BODY = 2000;

    /** Longest plausible mode or disposition name; the spec says varchar(30). */
    private const MAX_LIST_ITEM = 64;

    /** A compromised endpoint should not be able to flood a dropdown either. */
    private const MAX_LIST_ITEMS = 200;

    /**
     * Refusals worth explaining in the agent's own words.
     *
     * Matched on the shortest distinctive fragment rather than the document's
     * full sentence. The live box says "Agent not in manual/progressive mode"
     * where the spec says "manual or progressive" — keying on the whole
     * sentence meant that refusal went unrecognised and, worse, the automatic
     * mode fix never fired. Fragments survive that kind of wording drift.
     */
    private const REFUSALS = [
        'not in manual' => ['wrong_mode', 'Your campaign is not in manual or progressive mode.'],
        'not login'     => ['not_logged_in', 'You are not logged into Smart Agent. Sign in there first, then try again.'],
        'busy'          => ['busy', 'You are already on a call or in wrap-up. Finish that first, then try again.'],
        'same mode'     => ['same_mode', 'You are already in that mode.'],
        'not idle'      => ['not_idle', 'You are on a call, so you cannot log out yet.'],
        'not on call'   => ['not_on_call', 'There is no active call.'],
        'not on hold'   => ['not_on_hold', 'That call is not on hold.'],
        'not on mute'   => ['not_on_mute', 'That call is not muted.'],
    ];

    /** What every non-dial action returns for a Convoque agent — see dialConvoque(). */
    private const CONVOQUE_UNSUPPORTED = [
        'result'  => 'unsupported',
        'message' => 'This action is not available for your dialer.',
        'raw'     => '',
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

    /**
     * This agent's config if — and only if — they are wired to Convoque
     * rather than the shared CentroVision gateway. Every other method in
     * this class checks this first and takes a completely separate path for
     * a "yes"; nothing below this changes what happens for anyone else.
     */
    private function convoqueAgent(User $user): ?array
    {
        $agent = $this->agentFor($user);

        return ($agent && ($agent['vendor'] ?? null) === 'convoque') ? $agent : null;
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
        if ($agent = $this->convoqueAgent($user)) {
            return $this->dialConvoque($user, $agent, $phone);
        }

        // The correlation id that ties this click to a CDR row hours later.
        // CentroVision does not echo it yet -- see centrovision-required-v1.yaml
        // -- but sending it costs nothing, and the ingest side already stores
        // it the moment they start. Until then it is still worth having in the
        // log, because it is the only thing linking "who pressed the button" to
        // an entry on the dialer's own screen.
        $refId = (string) Str::uuid();

        $result = $this->send($user, 'DIAL', ['line' => 0, 'phone' => $phone, 'ref_id' => $refId]);
        $result['ref_id'] = $refId;

        if ($result['result'] !== 'wrong_mode' || !$autoFixMode) {
            return $result;
        }

        Log::info("CLICK_TO_CALL_MODE_FIX: user {$user->id} is not in a dialable mode; switching to manual");

        $switch = $this->switchToManual($user);

        if ($switch['result'] !== 'success') {
            // Report why the mode could not be changed, not the original
            // refusal — that is the part the agent can act on.
            return $switch + ['mode_fix_attempted' => true, 'ref_id' => $refId];
        }

        // Deliberately the same ref_id as the first attempt: this is one dial
        // from the agent's point of view, and minting a second id here would
        // leave the failed attempt correlating to nothing.
        $retry = $this->send($user, 'DIAL', ['line' => 0, 'phone' => $phone, 'ref_id' => $refId]);
        $retry['ref_id'] = $refId;
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
        if ($this->convoqueAgent($user)) {
            return self::CONVOQUE_UNSUPPORTED;
        }

        return $this->send($user, 'HANGUP', ['line' => 0]);
    }

    public function dispose(User $user, string $disposition, ?string $subDisposition = null): array
    {
        if ($this->convoqueAgent($user)) {
            return self::CONVOQUE_UNSUPPORTED;
        }

        $params = ['disposition' => $disposition];

        if ($subDisposition !== null && $subDisposition !== '') {
            $params['sub_disposition'] = $subDisposition;
        }

        return $this->send($user, 'DISPOSE', $params);
    }

    public function lineStatus(User $user): array
    {
        if ($this->convoqueAgent($user)) {
            return self::CONVOQUE_UNSUPPORTED;
        }

        return $this->send($user, 'LINESTATUS', []);
    }

    // ------------------------------------------------------------------ lists

    /**
     * @return array<int|string,string> mode id => mode name
     *
     * Convoque has no mode concept — returns empty, same as a campaign the
     * dialer genuinely has no modes for, rather than an error the caller has
     * to special-case.
     */
    public function modeList(User $user): array
    {
        if ($this->convoqueAgent($user)) {
            return [];
        }

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
        if ($this->convoqueAgent($user)) {
            return [];
        }

        return $this->splitList($this->send($user, 'LIST', ['list' => 'disposition'])['raw']);
    }

    /** @return string[] */
    public function subDispositions(User $user, string $disposition): array
    {
        if ($this->convoqueAgent($user)) {
            return [];
        }

        return $this->splitList(
            $this->send($user, 'LIST', ['list' => 'sub_disposition', 'disposition' => $disposition])['raw']
        );
    }

    /**
     * Scrub the dialer's reply before it goes anywhere near a log line, a JSON
     * body or the page.
     *
     * Markup is stripped rather than escaped, control characters are dropped so
     * nothing can forge log lines with embedded newlines, and the whole thing is
     * truncated. The front end also assigns it with textContent rather than
     * innerHTML, so this is defence in depth rather than the only guard.
     */
    private function sanitiseBody(string $body): string
    {
        $clean = strip_tags($body);
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $clean) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        $clean = trim($clean);

        if (mb_strlen($clean) > self::MAX_BODY) {
            Log::warning('CLICK_TO_CALL_OVERSIZED_RESPONSE: ' . mb_strlen($clean) . ' chars, truncated');
            $clean = mb_substr($clean, 0, self::MAX_BODY);
        }

        return $clean;
    }

    /**
     * Lists arrive pipe-delimited and sometimes end with a stray full stop.
     *
     * Entries become dropdown values that are later sent back to the dialer, so
     * anything that does not look like a name is dropped rather than trusted.
     */
    private function splitList(string $raw): array
    {
        // A refusal is not a list.
        foreach (array_keys(self::REFUSALS) as $refusal) {
            if (str_contains(strtolower($raw), $refusal)) {
                return [];
            }
        }

        $items = [];

        foreach (explode('|', $raw) as $entry) {
            $entry = trim(rtrim(trim($entry), '.'));

            if ($entry === '' || mb_strlen($entry) > self::MAX_LIST_ITEM) {
                continue;
            }

            // Names, ids and the "2=Manual" form only — no quotes, angle
            // brackets, ampersands or anything else that would be awkward in a
            // URL or a page.
            if (!preg_match('/^[\p{L}\p{N} _\-\.\/\(\)=]+$/u', $entry)) {
                Log::warning("CLICK_TO_CALL_SUSPICIOUS_LIST_ITEM: " . mb_substr($entry, 0, 80));
                continue;
            }

            $items[] = $entry;

            if (count($items) >= self::MAX_LIST_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * A disposition arrives from the browser and is sent straight back to the
     * dialer, so confirm the campaign actually offers it. When the list cannot
     * be fetched — the agent may have dropped out of session — fall back to a
     * shape check rather than blocking a legitimate save.
     */
    public function isKnownDisposition(User $user, string $disposition): bool
    {
        $known = $this->dispositions($user);

        if ($known !== []) {
            return in_array($disposition, $known, true);
        }

        return mb_strlen($disposition) <= self::MAX_LIST_ITEM
            && (bool) preg_match('/^[\p{L}\p{N} _\-\.\/\(\)]+$/u', $disposition);
    }

    // --------------------------------------------------------------- transport

    /**
     * Convoque's whole API surface, for the one agent wired to it: a GET that
     * returns JSON {"status":"success"|..., "traceID":..., "message":...} —
     * nothing like CentroVision's plain-text-body, query-action shape, so this
     * does not reuse send() at all rather than force a second protocol through
     * the same method.
     */
    private function dialConvoque(User $user, array $agent, string $phone): array
    {
        $refId = (string) Str::uuid();
        $base  = rtrim((string) config('access.click_to_call.convoque.base_url'), '/');

        // A plain array lookup, not config()'s dot notation — an email like
        // newbiz@hitechgroup.in contains its own dots, which dot notation
        // would read as nested config keys and never find.
        $authCodes = (array) config('access.click_to_call.convoque.auth_codes', []);
        $authCode  = $authCodes[strtolower($user->email)] ?? null;

        if (!$authCode) {
            Log::error("CLICK_TO_CALL_MISCONFIGURED (convoque): no auth code set for {$user->email}");

            return [
                'result'  => 'unreachable',
                'message' => 'Your dialer is not fully configured yet. Contact IT.',
                'raw'     => '',
                'ref_id'  => $refId,
            ];
        }

        Log::info("CLICK_TO_CALL DIAL (convoque): extension {$agent['extension']} -> {$phone} ref={$refId}");

        try {
            $response = Http::timeout((int) config('access.click_to_call.timeout', 10))
                ->get($base . '/ConvoqueAPI/placeACall_cri.jsp', [
                    'src'      => $agent['extension'],
                    'dst'      => $phone,
                    'authCode' => $authCode,
                ]);
        } catch (\Throwable $e) {
            Log::error("CLICK_TO_CALL_UNREACHABLE (convoque): " . $e->getMessage());

            return [
                'result'  => 'unreachable',
                'message' => 'Could not reach the dialer. It may be down or blocking this server.',
                'raw'     => '',
                'ref_id'  => $refId,
            ];
        }

        $raw = $this->sanitiseBody($response->body());
        Log::info("CLICK_TO_CALL_RESPONSE (convoque): {$raw}");

        $json = json_decode($response->body(), true);

        if (is_array($json) && ($json['status'] ?? null) === 'success') {
            return [
                'result'  => 'success',
                'message' => 'Calling now — pick up your agent phone.',
                'detail'  => $this->displayable($raw),
                'raw'     => $raw,
                'ref_id'  => $refId,
            ];
        }

        return [
            'result'  => 'error',
            'message' => 'The dialer refused the request.',
            'detail'  => $this->displayable($raw),
            'raw'     => $raw,
            'ref_id'  => $refId,
        ];
    }

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

        $raw   = $this->sanitiseBody($response->body());
        $lower = strtolower($raw);
        Log::info("CLICK_TO_CALL_RESPONSE [{$action}][{$user->id}]: {$raw}");

        if (isset(self::SUCCESS[$action]) && str_contains($lower, self::SUCCESS[$action])) {
            return [
                'result'  => 'success',
                'message' => $this->successMessage($action),
                'detail'  => $this->displayable($raw),
                'raw'     => $raw,
            ];
        }

        foreach (self::REFUSALS as $needle => [$code, $message]) {
            if (str_contains($lower, $needle)) {
                return [
                    'result'  => $code,
                    'message' => $message,
                    'detail'  => $this->displayable($raw),
                    'raw'     => $raw,
                ];
            }
        }

        // LIST and LINESTATUS have no success keyword — a non-refusal body is
        // the payload itself.
        if (in_array($action, ['LIST', 'LINESTATUS'], true) && $raw !== '') {
            return ['result' => 'success', 'message' => $raw, 'raw' => $raw];
        }

        // Documented catch-all is a bare "Error"; anything unrecognised lands
        // here too rather than being reported as though it worked.
        //
        // The reply is surfaced as 'detail' rather than folded into 'message',
        // and only after displayable() has reduced it to a plain phrase. That
        // matters here: an unrecognised reply is usually the dialer wording
        // something differently, and hiding it entirely once cost us a silent
        // failure of the automatic mode fix.
        return [
            'result'  => 'error',
            'message' => 'The dialer refused the request.',
            'detail'  => $this->displayable($raw),
            'raw'     => $raw,
        ];
    }

    /**
     * The dialer's own words, reduced to something safe to put on the page.
     *
     * Keeping this visible is worth real money in diagnosis — an unrecognised
     * reply is normally the vendor wording something differently, and we only
     * found the "manual/progressive" mismatch by reading it. Anything outside a
     * plain phrase is dropped, and it is rendered with textContent, so the worst
     * a hostile endpoint achieves is a short nonsense string.
     */
    private function displayable(string $raw): string
    {
        $clean = preg_replace('/[^\p{L}\p{N} \.\,\:\-\/\|]+/u', '', $raw) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return mb_strlen($clean) > 100 ? mb_substr($clean, 0, 100) . '...' : $clean;
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
