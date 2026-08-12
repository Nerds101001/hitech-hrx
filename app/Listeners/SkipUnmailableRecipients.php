<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;

/**
 * Cancels the mail channel for a recipient whose address cannot be delivered to.
 *
 * When an employee leaves, their address is deliberately mangled to
 * "someone@example.com_T<id>" so the account can no longer be used. Those people
 * stay wired into notification lists — as a former manager, or simply as an
 * older recipient — and the mail server rejects the address with a hard 553.
 *
 * That mattered far beyond the one message: Notification::send() mails its
 * recipients in a loop, so the exception aborted the send and every recipient
 * QUEUED BEHIND the bad address silently received nothing. A single ex-employee
 * in the list was enough to stop leave mail reaching the actual manager and HR.
 *
 * Returning false from this listener cancels just that one channel for that one
 * recipient; the database and push channels still run, and everybody else in the
 * same send is unaffected.
 */
class SkipUnmailableRecipients
{
    public function handle(NotificationSending $event): bool
    {
        if ($event->channel !== 'mail') {
            return true;
        }

        $address = $this->addressFor($event->notifiable);

        if ($address !== null && filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        Log::warning('Skipped mail to an undeliverable address', [
            'notifiable_id' => $event->notifiable->id ?? null,
            'address'       => $address,
            'notification'  => get_class($event->notification),
        ]);

        return false;
    }

    /** The address Laravel would actually mail, in whichever shape it comes. */
    private function addressFor($notifiable): ?string
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : ($notifiable->email ?? null);

        // routeNotificationForMail may return "address", [address], or
        // [address => name]; take the address in each case.
        if (is_array($route)) {
            $first = array_key_first($route);
            $route = is_string($first) ? $first : ($route[$first] ?? null);
        }

        return is_string($route) && $route !== '' ? $route : null;
    }
}
