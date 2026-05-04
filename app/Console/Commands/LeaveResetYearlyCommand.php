<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveAccrualService;

class LeaveResetYearlyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:reset-yearly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset yearly leave carry-forward balances on April 1st.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting yearly leave carry-forward reset...');
        
        LeaveAccrualService::resetYearlyCarryForward();

        $this->info('Yearly leave reset completed successfully.');
    }
}
