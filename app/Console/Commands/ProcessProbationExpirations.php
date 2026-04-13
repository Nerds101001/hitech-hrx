<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Probation\ProbationEvaluationRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessProbationExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'probation:process-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for employees whose probation ends today and notify their managers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        $employees = User::where('probation_end_date', '<=', $today)
            ->whereNull('probation_confirmed_at')
            ->whereNotNull('reporting_to_id')
            ->whereDoesntHave('probationEvaluations')
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            $manager = $employee->reportingTo; // Assuming the relation name is reportingTo
            
            if (!$manager) {
                // Fallback attempt: if relation is named differently
                $manager = User::find($employee->reporting_to_id);
            }

            if ($manager) {
                $manager->notify(new ProbationEvaluationRequest($employee));
                $this->info("Notified manager {$manager->name} for employee {$employee->name}");
                $count++;
            } else {
                $this->warn("No manager found for employee {$employee->name} (User ID: {$employee->id})");
            }
        }

        $this->info("Completed probation processing. {$count} notifications sent.");
        Log::info("Probation automation: {$count} notifications sent for {$today}");
    }
}
