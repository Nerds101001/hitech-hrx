<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Enums\UserAccountStatus;
use Illuminate\Console\Command;

class EnforceOnboardingDeadline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onboarding:enforce-deadline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate users who failed to complete onboarding within the deadline.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $overdueUsers = User::where('status', UserAccountStatus::ONBOARDING)
            ->whereNotNull('onboarding_deadline')
            ->where('onboarding_deadline', '<=', now())
            ->get();

        foreach ($overdueUsers as $user) {
            $user->status = UserAccountStatus::INACTIVE;
            $user->save();
            $this->info("User {$user->email} has been deactivated due to onboarding deadline expiration.");
        }

        $this->info(count($overdueUsers) . " users processed.");
    }
}
