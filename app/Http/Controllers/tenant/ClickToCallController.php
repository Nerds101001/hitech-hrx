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

        return response()->json($this->calls->dial($request->user(), $phone));
    }

    public function hangup(Request $request)
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        return response()->json($this->calls->hangup($request->user()));
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

        return response()->json($this->calls->dispose(
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
