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
     * Place a call for the signed-in agent.
     *
     * Authorisation is checked here rather than only on the button: the button
     * is hidden for everyone else, but a hidden button is not a permission.
     */
    public function dial(Request $request)
    {
        $user = $request->user();

        if (!$this->calls->canCall($user)) {
            return response()->json([
                'result'  => 'forbidden',
                'message' => 'Click to call is not enabled for your account.',
            ], 403);
        }

        $phone = $this->calls->normalisePhone((string) $request->input('phone'));

        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return response()->json([
                'result'  => 'invalid',
                'message' => 'Enter a valid phone number (10 to 15 digits).',
            ], 422);
        }

        return response()->json($this->calls->dial($user, $phone));
    }
}
