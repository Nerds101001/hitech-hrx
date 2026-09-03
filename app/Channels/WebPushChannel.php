<?php

namespace App\Channels;

use App\Models\PushSubscription;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Standard W3C Web Push (VAPID) -- browser notifications, independent of
 * Firebase. Reuses the same toFirebase() {title, body} payload every
 * notification already provides (see FirebaseChannel), since it's the same
 * data either way -- just a different delivery pipe.
 */
class WebPushChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFirebase')) {
            return;
        }

        $publicKey = config('webpush.public_key');
        $privateKey = config('webpush.private_key');
        if (empty($publicKey) || empty($privateKey)) {
            return; // Not configured yet -- silent no-op, same as FirebaseChannel with no token.
        }

        $payload = $notification->toFirebase($notifiable);
        if (empty($payload['title']) || empty($payload['body'])) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->id)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $body = json_encode([
            'title' => $payload['title'],
            'body' => $payload['body'],
            'url' => $payload['action_url'] ?? url('/'),
        ]);

        foreach ($subscriptions as $sub) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'publicKey' => $sub->p256dh,
                        'authToken' => $sub->auth,
                    ]),
                    $body
                );
            } catch (Exception $e) {
                Log::error("Failed to queue web push for subscription {$sub->id}: " . $e->getMessage());
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            // A permanently gone subscription (browser unsubscribed, cleared
            // site data, etc.) returns 404/410 -- the endpoint is dead, so
            // stop trying it instead of failing on it forever.
            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                PushSubscription::where('endpoint', $report->getRequest()->getUri())->delete();
            } else {
                Log::warning("Web push delivery failed: " . $report->getReason());
            }
        }
    }
}
