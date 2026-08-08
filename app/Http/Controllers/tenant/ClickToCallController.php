<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Services\ClickToCallService;
use Illuminate\Http\Request;

class ClickToCallController extends Controller
{
    public function __construct(private ClickToCallService $calls)
    {
    }

    /**
     * Authorisation is checked on every action, not just on the button. The
     * button is hidden for everyone else, but a hidden button is not a
     * permission.
     */
    private function deny(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if ($this->calls->canCall($request->user())) {
            return null;
        }

        return response()->json([
            'result'  => 'forbidden',
            'message' => 'Click to call is not enabled for your account.',
        ], 403);
    }

    /**
     * The dialer's own words stay server-side. 'raw' is kept in the log for
     * diagnosis, but only the result code and a message we composed are handed
     * to the browser — there is no reason to relay a third party's bytes.
     */
    private function reply(array $outcome): \Illuminate\Http\JsonResponse
    {
        return response()->json(\Illuminate\Support\Arr::except($outcome, ['raw']));
    }

    /** Place a call. Fixes the agent's mode first if that is what is blocking it. */
    public function dial(Request $request)
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $phone = $this->calls->normalisePhone((string) $request->input('phone'));

        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return response()->json([
                'result'  => 'invalid',
                'message' => 'Enter a valid phone number (10 to 15 digits).',
            ], 422);
        }

        return $this->reply($this->calls->dial($request->user(), $phone));
    }

    public function hangup(Request $request)
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        return $this->reply($this->calls->hangup($request->user()));
    }

    /** Save the outcome of the call the agent just had. */
    public function dispose(Request $request)
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $disposition = trim((string) $request->input('disposition'));

        if ($disposition === '') {
            return response()->json([
                'result'  => 'invalid',
                'message' => 'Choose a disposition first.',
            ], 422);
        }

        // The value came from the browser and goes straight back out to the
        // dialer, so check the campaign actually offers it.
        if (!$this->calls->isKnownDisposition($request->user(), $disposition)) {
            return response()->json([
                'result'  => 'invalid',
                'message' => 'That disposition is not available on your campaign.',
            ], 422);
        }

        return $this->reply($this->calls->dispose(
            $request->user(),
            $disposition,
            $request->input('sub_disposition')
        ));
    }

    /**
     * Dispositions for the agent's campaign. Fetched live rather than stored,
     * so the dialer stays the single source of truth.
     */
    public function dispositions(Request $request)
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        return response()->json(['dispositions' => $this->calls->dispositions($request->user())]);
    }

    public function subDispositions(Request $request)
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $disposition = trim((string) $request->input('disposition'));

        return response()->json([
            'sub_dispositions' => $disposition === ''
                ? []
                : $this->calls->subDispositions($request->user(), $disposition),
        ]);
    }
}
