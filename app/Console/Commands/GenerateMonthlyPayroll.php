<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyPayroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:auto-generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically generate payroll for all active employees as draft at month-end';

    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        parent::__construct();
        $this->payrollService = $payrollService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;

        $this->info("Starting automated payroll generation for $month/$year...");
        Log::info("Automated payroll generation started for $month/$year.");

        try {
            // Generate as 'generated' status (which acts as a draft)
            $count = $this->payrollService->processBulk($month, $year, 'generated');
            
            $this->info("Successfully generated $count payroll records in draft state.");
            Log::info("Automated payroll generation completed. $count records created.");
        } catch (\Exception $e) {
            $this->error("Failed to generate automated payroll: " . $e->getMessage());
            Log::error("Automated payroll generation failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
