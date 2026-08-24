<?php

namespace App\Console\Commands;

use App\Models\TaskSchedule;
use App\Services\TaskScheduleService;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring';

    protected $description = 'Fire every active recurring task schedule that matches today, creating one task per target person.';

    public function handle(): void
    {
        $fired = 0;
        $created = 0;

        foreach (TaskSchedule::where('is_active', true)->get() as $schedule) {
            $tasks = TaskScheduleService::fireIfDue($schedule);
            if ($tasks->isNotEmpty()) {
                $fired++;
                $created += $tasks->count();
            }
        }

        $this->info("{$fired} schedule(s) fired, {$created} task(s) created.");
    }
}
