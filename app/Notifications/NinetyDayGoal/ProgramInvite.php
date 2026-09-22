<?php

namespace App\Notifications\NinetyDayGoal;

use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent the moment an admin creates a 90-Day Goals program and invites someone into it. */
class ProgramInvite extends Notification
{
    use Queueable;

    private NinetyDayProgram $program;
    private NinetyDayRound $round;
    private string $title;
    private string $message;

    public function __construct(NinetyDayProgram $program, NinetyDayRound $round)
    {
        $this->program = $program;
        $this->round = $round;
        $this->title = 'New 90-Day Goals program: ' . $program->name;
        $this->message = (optional($program->creator)->getFullName() ?? 'An admin') . ' added you to "' . $program->name . '" — add your target customers within 3 days.';
    }

    /**
     * Demo/import accounts (sales@demo.com, the "(Import)" CSV accounts, etc.) have made-up
     * addresses that were never meant to be mailed — this app's local .env points at a real
     * Gmail account, so a fake address here would still be a real send attempt. Shared by
     * via() and by SendNinetyDayProgramInviteMail, which sends mail on its own queued path
     * and must apply the same guard rather than trusting via() to have run first.
     */
    public static function looksFakeAddress(?string $email): bool
    {
        $domain = strtolower((string) strrchr((string) $email, '@'));   // includes the leading "@"
        $fakeDomains = ['@demo.com', '@example.com', '@example.test', '@example.org'];

        return in_array($domain, $fakeDomains, true) || str_ends_with($domain, '.demo');
    }

    public function via(object $notifiable): array
    {
        // The in-app entry is a fast local insert, sent synchronously so it's visible the moment
        // the program is created. The actual email is never sent from here — it goes out through
        // SendNinetyDayProgramInviteMail, queued separately so a slow or unreachable mail host
        // can never hold up the page (see that job's docblock for why).
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been added to "' . $this->program->name . '"')
            ->view('emails.ninety_day_goal_invite', [
                'notifiable'    => $notifiable,
                'programName'   => $this->program->name,
                'startsOn'      => $this->round->starts_on->format('d M Y'),
                'endsOn'        => $this->round->ends_on->format('d M Y'),
                'deadline'      => $this->round->starts_on->copy()->addDays(3)->format('d M Y'),
                'createdByName' => optional($this->program->creator)->getFullName() ?? 'your organisation',
                'actionUrl'     => url('/performance/ninety-day-goals'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'message'     => $this->message,
            'program_id'  => $this->program->id,
            'round_id'    => $this->round->id,
            'action_url'  => url('/performance/ninety-day-goals'),
        ];
    }
}
