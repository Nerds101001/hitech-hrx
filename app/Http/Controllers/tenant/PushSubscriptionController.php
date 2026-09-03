<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Standard W3C Web Push subscriptions (VAPID) -- browser notifications, not
 * the mobile app's Firebase token flow. One row per browser/device; a user
 * can hold several.
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string|max:2000',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        $user = Auth::user();
        $endpointHash = hash('sha256', $data['endpoint']);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'user_id'     => $user->id,
                'endpoint'    => $data['endpoint'],
                'p256dh'      => $data['keys']['p256dh'],
                'auth'        => $data['keys']['auth'],
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['status' => 'success']);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string|max:2000']);

        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['status' => 'success']);
    }
}
