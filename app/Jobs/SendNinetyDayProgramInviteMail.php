<?php

namespace App\Jobs;

use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Notifications\NinetyDayGoal\ProgramInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends one program-invite email. Split out of ProgramInvite's own mail channel and queued
 * (on the 'database' connection, regardless of the app's default QUEUE_CONNECTION) so that
 * inviting a large batch of people never makes the admin's browser wait on real SMTP —
 * app()->terminating() only *looks* deferred; the local dev server has no
 * fastcgi_finish_request, so the HTTP response itself used to sit blocked behind every
 * synchronous send, once as long as 8–10 minutes for a few dozen invites. The in-app
 * ('database') notification channel stays synchronous — it's a fast local insert and is
 * what gives the admin instant visibility, so it isn't moved here.
 *
 * Run `php artisan queue:work --queue=notifications` (or the equivalent supervisor entry
 * in prod) for these to actually go out; until a worker is running they queue harmlessly.
 */
class SendNinetyDayProgramInviteMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    private int $programId;
    private int $roundId;

    public function __construct(NinetyDayProgram $program, NinetyDayRound $round)
    {
        $this->programId = $program->id;
        $this->roundId = $round->id;
        // Set here, not as class properties — Queueable already declares $connection/$queue,
        // and redeclaring them with defaults collides with the trait at composition time.
        $this->onConnection('database');
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $program = NinetyDayProgram::find($this->programId);
        $round = NinetyDayRound::with('user')->find($this->roundId);
        if (!$program || !$round || !$round->user) {
            return;
        }

        if (ProgramInvite::looksFakeAddress($round->user->email)) {
            return;
        }

        try {
            $round->user->notifyNow(new ProgramInvite($program, $round), ['mail']);
        } catch (\Throwable $e) {
            Log::warning("90-Day Goals program invite email failed for user {$round->user_id}: " . $e->getMessage());
        }
    }
}
