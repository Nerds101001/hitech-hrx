<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('logs:clear', function () {

  exec('rm -f ' . storage_path('logs/*.log'));

  exec('rm -f ' . base_path('*.log'));

  $this->comment('Logs have been cleared!');

})->describe('Clear log files');

// routes:validate-menu is a real command class (RouteMenuValidator) — Laravel
// auto-discovers it from app/Console/Commands, no registration needed here.
// Properly schedule the class-based leave:accrue command instead of a recursive closure
Schedule::command('leave:accrue')->monthly();

// leave:grant-short-leave — class-based, scheduled via facade so signature is preserved

Schedule::command('leave:grant-short-leave')->monthlyOn(1, '00:05');

// The five commands below were each previously registered as
// Artisan::command('name', fn () => $this->call('name')) — a closure whose
// only job was to call the command by its own name, which just re-enters
// the same closure forever until the PHP stack overflows. Every one of
// these already exists as a real Command class (AutoRejectLeavesCommand,
// SendLeaveDailyDigest, EnforceOnboardingDeadline, ProcessProbationExpirations)
// that Laravel auto-discovers; scheduling them via Schedule::command(), the
// same pattern already used above for leave:accrue, needs no closure at all.
// onboarding:enforce-deadlines was also a typo for the real command's
// signature, onboarding:enforce-deadline (singular) — silently never
// resolving even before the recursion, since Artisan::command() defines
// whatever name you give it rather than validating it against an existing
// command.
Schedule::command('leave:auto-reject-expired')->daily()->description('Auto-reject leave requests pending for more than 48 hours');
Schedule::command('leave:daily-digest')->dailyAt('10:15')->description('Send daily digest of team members on leave');
Schedule::command('onboarding:enforce-deadline')->daily()->description('Enforce onboarding completion deadlines');
Schedule::command('probation:process-expirations')->daily()->description('Process employee probation expirations');

Schedule::command('hrx:transfer-newbiz-ccare')->daily();
