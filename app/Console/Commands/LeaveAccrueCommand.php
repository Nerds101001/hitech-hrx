<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveAccrualService;

class LeaveAccrueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:accrue {--user= : Accrue for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Accrue monthly leave quotas for users based on their profiles.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly leave accrual...');
        
        LeaveAccrualService::accrueMonthly();

        $this->info('Leave accrual completed successfully.');
    }
}
