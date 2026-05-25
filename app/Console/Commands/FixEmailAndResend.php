<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Onboarding\OnboardingInvite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixEmailAndResend extends Command
{
    protected $signature = 'user:fix-email-resend {oldEmail} {newEmail}';
    protected $description = 'Fix a user email typo and resend onboarding email';

    public function handle()
    {
        $oldEmail = $this->argument('oldEmail');
        $newEmail = $this->argument('newEmail');

        $user = User::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [strtolower($oldEmail)])
            ->whereNull('deleted_at')
            ->first();

        if (!$user) {
            $this->error("User {$oldEmail} not found.");
            return Command::FAILURE;
        }

        $this->line("Found: ID={$user->id}, Name={$user->first_name} {$user->last_name}, Email={$user->email}");

        // Check new email not already taken
        $exists = User::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [strtolower($newEmail)])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            $this->error("Email {$newEmail} is already in use.");
            return Command::FAILURE;
        }

        // Update email
        $user->email = $newEmail;
        $user->save();
        $this->info("Email updated: {$oldEmail} → {$newEmail}");

        // Send onboarding email
        $tempPassword = 'Hitech@' . rand(1000, 9999);
        $user->password = bcrypt($tempPassword);
        $user->save();

        try {
            $user->notify(new OnboardingInvite($user, $tempPassword));
            $this->info("Onboarding email sent to {$newEmail}");
        } catch (\Exception $e) {
            $this->warn("Email failed: " . $e->getMessage());
        }

        $this->line("Credentials => Email: {$newEmail} | Password: {$tempPassword}");
        return Command::SUCCESS;
    }
}
