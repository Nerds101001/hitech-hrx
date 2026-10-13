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

// leave:accrue and leave:daily-digest are scheduled in bootstrap/app.php's
// withSchedule() block, not here — they used to ALSO be registered here,
// meaning both would fire independently every run. leave:accrue survived
// that unharmed only because LeaveAccrualService has its own idempotency
// guard (added specifically to make a second pass safe); leave:daily-digest
// has no such guard, so its duplicate really was sending each team's digest
// twice a day, once from each registration's own time. bootstrap/app.php
// is the more complete picture for scheduling — it sequences
// leave:reconcile-attendance to run before the digest so the numbers it
// shows are same-day-accurate — so that's the one source of truth to keep.

// leave:grant-short-leave — class-based, scheduled via facade so signature is preserved
Schedule::command('leave:grant-short-leave')->monthlyOn(1, '00:05');

// The four commands below were each previously registered as
// Artisan::command('name', fn () => $this->call('name')) — a closure whose
// only job was to call the command by its own name, which just re-enters
// the same closure forever until the PHP stack overflows. Every one of
// these already exists as a real Command class (AutoRejectLeavesCommand,
// EnforceOnboardingDeadline, ProcessProbationExpirations) that Laravel
// auto-discovers; scheduling them via Schedule::command(), the same
// pattern already used for leave:grant-short-leave above, needs no
// closure at all.
// onboarding:enforce-deadlines was also a typo for the real command's
// signature, onboarding:enforce-deadline (singular) — silently never
// resolving even before the recursion, since Artisan::command() defines
// whatever name you give it rather than validating it against an existing
// command.
// Disabled 2026-08-26 at the user's request: pending leave requests should
// stay pending indefinitely for now rather than being auto-rejected after
// 48 hours. The command class itself (AutoRejectLeavesCommand) is untouched
// and can still be run manually via `artisan leave:auto-reject-expired`.
// Schedule::command('leave:auto-reject-expired')->daily()->description('Auto-reject leave requests pending for more than 48 hours');
Schedule::command('onboarding:enforce-deadline')->daily()->description('Enforce onboarding completion deadlines');
Schedule::command('probation:process-expirations')->daily()->description('Process employee probation expirations');
Schedule::command('tasks:generate-recurring')->dailyAt('06:00')->description('Fire recurring task schedules due today');
Schedule::command('tasks:daily-digest')->dailyAt('07:30')->description('Send each person their single daily task email (due/overdue/pending-reply/CC backlog) - replaces a separate email per reply or update');
Schedule::command('tasks:notify-overdue')->dailyAt('09:00')->description('Notify staff whose assigned task is overdue');
Schedule::command('tasks:notify-expected-date')->dailyAt('09:00')->description("Remind staff the day their own completion_expected_date arrives");
Schedule::command('tasks:notify-due-soon')->dailyAt('08:30')->description('Remind staff their assigned task is due soon (before it is due)');
Schedule::command('tasks:escalate-overdue')->dailyAt('09:30')->description("Escalate a long-overdue open task to the assignee's manager");

Schedule::command('hrx:transfer-newbiz-ccare')->daily();
// Sales Report 5-month rule: New Biz customers with sales in 5 months move to CCare.
Schedule::command('sales:graduate-newbiz')->dailyAt('06:30')->description('Move New Biz customers with sales in 5 months to CCare');

// Keeps the audits table's monthly partitions rolling: extends the runway of
// future months so nothing ever falls into the pmax catch-all, and drops whole
// months past config('audit.retain_months'). Monthly is ample -- the migration
// lays down a year of partitions in advance, so this has eleven months of slack
// before anything could go wrong.
Schedule::command('audits:partitions --force')
    ->monthlyOn(1, '01:30')
    ->description('Roll audit partitions forward and prune past retention');
