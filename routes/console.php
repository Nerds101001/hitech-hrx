<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\RouteMenuValidator;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('logs:clear', function () {

  exec('rm -f ' . storage_path('logs/*.log'));

  exec('rm -f ' . base_path('*.log'));

  $this->comment('Logs have been cleared!');

})->describe('Clear log files');

Artisan::command('routes:validate-menu', function () {
  $this->call('routes:validate-menu');
})->describe('Validate route-menu consistency');
Artisan::command('leave:accrue', function () {
  $this->call('leave:accrue');
})->describe('Accrue monthly leave quotas')->monthly();

Artisan::command('leave:auto-reject-expired', function () {
    $this->call('leave:auto-reject-expired');
})->describe('Auto-reject leave requests pending for more than 48 hours')->daily();

Artisan::command('leave:daily-digest', function () {
    $this->call('leave:daily-digest');
})->describe('Send daily digest of team members on leave')->dailyAt('08:00');

Artisan::command('onboarding:enforce-deadlines', function () {
    $this->call('onboarding:enforce-deadlines');
})->describe('Enforce onboarding completion deadlines')->daily();

Artisan::command('probation:process-expirations', function () {
    $this->call('probation:process-expirations');
})->describe('Process employee probation expirations')->daily();
