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
