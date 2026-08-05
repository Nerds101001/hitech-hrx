<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Gives every account behind the login page's DEV QUICK ACCESS panel a known
 * local password, so those buttons keep working after the local database is
 * refreshed from production (that import overwrites the password hashes, which
 * is what silently breaks quick login — see DemoAccountsSeeder for the
 * *@demo.com equivalents).
 *
 * These are REAL people's accounts copied into the local database for testing,
 * so this seeder REFUSES to run outside a local environment. Running it on
 * production would reset real users' passwords.
 *
 *   php artisan db:seed --class=QuickLoginDevSeeder
 */
class QuickLoginDevSeeder extends Seeder
{
    /** The password every quick-login button posts (see login.blade.php). */
    private const DEV_PASSWORD = 'password';

    /** email => label shown on the button, for readable output. */
    private const ACCOUNTS = [
        'ai@rustx.com'              => 'Admin — Nagender',
        'suchita@rustx.com'         => 'HR — Suchita',
        'pa1@rustx.com'             => 'Delegate — Heena',
        'newbiz@doctorrust.com'     => 'Sales — Navin Joshi',
        'ccare5@rustx.com'          => 'CCare — Shallu',
        'accounts5@rustx.com'       => 'Accounts — Shubham',
        'ccare4@rustx.com'          => 'CCare — Nidhi Sharma',
        'ccare2@rustx.com'          => 'CCare/New Biz — Poonam Sharma',
        'export3@rustx.com'         => 'Export — Deblina Layek',
        'finance@rustx.com'         => 'Finance Manager — Arpana Katyal',
        'priyanshuchoker1@gmail.com' => 'Onboarding',
        'tanisharana0910@gmail.com' => 'Onboarding — submitted',
        'jaiprabhu18@gmail.com'     => 'Onboarding — submitted + training',
    ];

    public function run(): void
    {
        if (!app()->isLocal()) {
            $this->command->error('QuickLoginDevSeeder is local-only — refusing to run in ' . app()->environment() . '.');
            return;
        }

        $set = 0;
        $missing = [];

        foreach (self::ACCOUNTS as $email => $label) {
            // Emails in this table are inconsistently cased, so match case-insensitively.
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

            if (!$user) {
                $missing[] = "{$label} <{$email}>";
                continue;
            }

            $user->password = Hash::make(self::DEV_PASSWORD);
            $user->save();
            $set++;
            $this->command->info("  ✅ {$label} <{$user->email}>");
        }

        $this->command->info("Set the local dev password on {$set} account(s).");

        foreach ($missing as $m) {
            $this->command->warn("  ⚠ not in this database: {$m}");
        }
    }
}
