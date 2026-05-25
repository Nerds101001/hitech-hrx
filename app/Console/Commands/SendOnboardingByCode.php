<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Onboarding\OnboardingInvite;
use Illuminate\Console\Command;

class SendOnboardingByCode extends Command
{
    protected $signature = 'onboarding:send-by-code {codes*}';
    protected $description = 'Send onboarding email to employees by their code';

    public function handle()
    {
        $codes = $this->argument('codes');

        foreach ($codes as $code) {
            $this->info("\n--- Employee Code: {$code} ---");

            $user = User::withoutGlobalScopes()
                ->where('code', $code)
                ->orWhereRaw('LOWER(email) = ?', [strtolower($code)])
                ->whereNull('deleted_at')
                ->first();

            if (!$user) {
                $this->error("Not found: code {$code}");
                continue;
            }

            $status = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
            $this->line("Name   : {$user->first_name} {$user->last_name}");
            $this->line("Email  : {$user->email}");
            $this->line("Status : {$status}");

            // Set status to onboarding
            $user->status = \App\Enums\UserAccountStatus::ONBOARDING;
            $user->onboarding_at = now();
            $user->onboarding_deadline = now()->addDays(3);

            $tempPassword = 'Hitech@' . rand(1000, 9999);
            $user->password = bcrypt($tempPassword);
            $user->save();

            try {
                $user->notify(new OnboardingInvite($user, $tempPassword));
                $this->info("SUCCESS: Email sent to {$user->email}");
            } catch (\Exception $e) {
                $this->warn("Email failed: " . $e->getMessage());
            }

            $this->line("Credentials => Email: {$user->email} | Password: {$tempPassword}");
        }

        $this->info("\nDone.");
        return Command::SUCCESS;
    }
}
