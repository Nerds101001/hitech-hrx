<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Enums\UserAccountStatus;
use App\Notifications\Onboarding\OnboardingInvite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportRamgarhStaff extends Command
{
    protected $signature = 'import:ramgarh-staff {file}';
    protected $description = 'Import staff from Ramgarh CSV and initiate onboarding';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // Skip header

        $count = 0;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 10) continue;

            // Map columns based on the CSV structure:
            // 0: em_id, 1: em_code, 2: NAME, 3: DESIGNATION, 4: DESIGNATION ID, 5: department (code), 
            // 6: FATHER NAME, 7: DATE OG JOINING, 8: DATE OF BIRTH, 9: BANK NAME, 10: MOBILE, 
            // 11: PAN CARD, 12: AADHAR, 13: ACCOUNT NUMBER, 14: IFSC, 15: Department (name), 16: Unit, 17: unit id

            $emCode = trim($data[1]);
            $name = trim($data[2]);
            $email = trim($data[3]);
            $designationId = trim($data[5]);
            $deptName = trim($data[16]);
            $fatherName = trim($data[7]);
            $doj = trim($data[8]);
            $dob = trim($data[9]);
            $mobile = trim($data[11]);
            $pan = trim($data[12]);
            $aadhaar = trim($data[13]);
            $accNo = trim($data[14]);
            $ifsc = trim($data[15]);
            $unitId = trim($data[18]);

            if (empty($email)) {
                $this->warn("Skipping row for $name: Email missing");
                continue;
            }

            // Check if user already exists
            if (User::where('email', $email)->orWhere('phone', $mobile)->exists()) {
                $this->warn("User already exists: $name ($email)");
                continue;
            }

            DB::beginTransaction();
            try {
                $plainPassword = Str::random(10);
                
                // Parse dates
                try {
                    $joiningDate = \Carbon\Carbon::createFromFormat('j-M-y', $doj)->format('Y-m-d');
                } catch (\Exception $e) {
                    $joiningDate = now()->toDateString();
                }

                try {
                    $birthDate = \Carbon\Carbon::createFromFormat('j-M-y', $dob)->format('Y-m-d');
                } catch (\Exception $e) {
                    $birthDate = null;
                }

                // Find Department by name if ID not reliable
                $dept = Department::where('name', 'like', "%$deptName%")->first();
                $deptId = $dept ? $dept->id : null;

                // Safely find or ignore designation
                $actualDesignationId = null;
                if (!empty($designationId)) {
                    $designationExists = Designation::where('id', $designationId)->exists();
                    if ($designationExists) {
                        $actualDesignationId = $designationId;
                    }
                }

                $user = User::create([
                    'tenant_id' => 1,
                    'first_name' => explode(' ', $name)[0],
                    'last_name' => count(explode(' ', $name)) > 1 ? implode(' ', array_slice(explode(' ', $name), 1)) : '',
                    'name' => $name,
                    'email' => $email,
                    'phone' => $mobile,
                    'code' => !empty($emCode) ? $emCode : 'TEMP-' . strtoupper(Str::random(6)),
                    'department_id' => $deptId,
                    'designation_id' => $actualDesignationId,
                    'reporting_to_id' => 1,
                    'site_id' => $unitId,
                    'date_of_joining' => $joiningDate,
                    'dob' => $birthDate,
                    'father_name' => $fatherName,
                    'aadhaar_no' => $aadhaar,
                    'pan_no' => $pan,
                    'status' => UserAccountStatus::ONBOARDING,
                    'onboarding_at' => now(),
                    'onboarding_deadline' => now()->addDays(7),
                    'password' => bcrypt($plainPassword),
                ]);

                // Assign Employee Role
                $role = Role::where('name', 'employee')->first();
                if ($role) {
                    $user->roles()->sync([$role->id]);
                }

                // Create Bank details if provided
                if (!empty($accNo)) {
                    $user->bankAccount()->create([
                        'bank_name' => $data[10] ?? 'N/A', // Corrected index for BANK NAME in CSV is 10
                        'account_number' => $accNo,
                        'bank_code' => $ifsc,
                        'account_name' => $name,
                        'tenant_id' => 1
                    ]);
                }

                DB::commit();
                $this->info("User Created: $name ($email)");

                // Send Onboarding Email (Outside transaction so it doesn't roll back the user)
                try {
                    $user->notify(new OnboardingInvite($user, $plainPassword));
                    $this->info("Invite Sent: $email");
                } catch (\Exception $e) {
                    $this->warn("Invite failed for $email: " . $e->getMessage());
                }

                $count++;
                
                // Sleep for 2 seconds to avoid Hostinger mail limit
                sleep(2);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to import $name: " . $e->getMessage());
            }
        }

        fclose($handle);
        $this->info("Successfully imported $count staff members.");
    }
}
