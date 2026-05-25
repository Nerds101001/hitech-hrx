<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckOnboardingEmails extends Command
{
    protected $signature = 'onboarding:check-sent';
    protected $description = 'Show last 5 onboarding emails sent';

    public function handle()
    {
        $records = DB::table('notifications')
            ->where('type', 'LIKE', '%Onboarding%')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['notifiable_id', 'type', 'created_at']);

        if ($records->isEmpty()) {
            $this->warn('No onboarding notifications found in DB.');
            return;
        }

        $rows = [];
        foreach ($records as $n) {
            $user = DB::table('users')->where('id', $n->notifiable_id)->first();
            $rows[] = [
                $n->created_at,
                $user->email ?? 'unknown',
                $user->first_name . ' ' . ($user->last_name ?? ''),
                class_basename($n->type),
            ];
        }

        $this->table(['Sent At', 'Email', 'Name', 'Type'], $rows);
    }
}
