<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncBiometricMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hrx:sync-biometric {csv_path : Path to the CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Maps Biometric IDs to existing Employees via Employee ID (code)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = $this->argument('csv_path');

        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: $csvPath");
            return 1;
        }

        $this->info("Starting biometric ID mapping...");
        
        $file = fopen($csvPath, 'r');
        $headers = fgetcsv($file); // Skip header row (Employee ID,First Name,Last Name,Biometric ID)

        $updatedCount = 0;
        $notFoundCount = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== FALSE) {
            if (empty($row[0]) || empty($row[3])) {
                continue;
            }

            $empCode = trim($row[0]);
            $biometricId = trim($row[3]);

            // Find existing user by code (Employee ID)
            $user = User::where('code', $empCode)->first();

            if ($user) {
                // UPDATE ONLY biometric_id - DO NOT touch other data
                $user->update(['biometric_id' => $biometricId]);
                $this->line("Updated [{$empCode}]: {$user->getFullName()} -> Biometric: {$biometricId}");
                $updatedCount++;
            } else {
                $this->warn("User not found for Employee ID: {$empCode}");
                $notFoundCount++;
            }
        }

        fclose($file);

        $this->info("---------------------------------------");
        $this->info("Sync completed successfully!");
        $this->info("Updated: {$updatedCount}");
        $this->info("Not Found: {$notFoundCount}");
        $this->info("---------------------------------------");

        return 0;
    }
}
