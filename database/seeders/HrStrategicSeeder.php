<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Designation;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobStage;
use App\Models\User;
use App\Models\Site;
use App\Models\JobCategory;
use App\Enums\UserAccountStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HrStrategicSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        if (!$admin) return;

        // 1. Seed Departments and Designations
        $depts = ['Engineering', 'Sales', 'Marketing', 'Customer Support', 'HR', 'Finance'];
        foreach ($depts as $dept) {
            $d = Department::firstOrCreate(['name' => $dept], [
                'code' => strtoupper(substr($dept, 0, 3)),
                'created_by_id' => $admin->id
            ]);
            // Create a designation for each department
            Designation::updateOrCreate(
                ['name' => $dept . ' Lead', 'department_id' => $d->id],
                [
                    'code' => strtoupper(substr($dept, 0, 3)) . '-LD',
                    'created_by_id' => $admin->id
                ]
            );
        }
        $designations = Designation::all();

        // 2. Seed Users for Hiring Trends (Last 12 months)
        $existingCount = User::where('first_name', 'Strategic')->count();
        if ($existingCount < 50) {
            for ($i = $existingCount; $i < 50; $i++) {
                $joinedAt = Carbon::now()->subMonths(rand(0, 11))->subDays(rand(0, 28));
                $isInactive = rand(0, 10) > 8; // 20% attrition
                
                $uniqueSuffix = uniqid();
                $email = 'employee_' . $i . '_' . $uniqueSuffix . '@example.com';
                User::create([
                    'first_name' => 'Strategic',
                    'last_name' => 'Employee ' . $i,
                    'email' => $email,
                    'user_name' => 'strat_emp_' . $i . '_' . $uniqueSuffix,
                    'password' => bcrypt('password'),
                    'date_of_joining' => $joinedAt,
                    'status' => UserAccountStatus::ACTIVE,
                    'relieved_at' => $isInactive ? $joinedAt->copy()->addMonths(rand(1, 4)) : null,
                    'designation_id' => $designations->random()->id,
                    'created_by_id' => $admin->id,
                    'address' => '123 Strategic Way',
                    'phone' => '9' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                    'gender' => 'male',
                    'code' => 'STRAT' . str_pad($i + $existingCount + rand(100, 999), 5, '0', STR_PAD_LEFT),
                ]);
                if ($isInactive) {
                    User::where('email', $email)->update(['status' => UserAccountStatus::INACTIVE]);
                }
            }
        }

        // 3. Seed Announcements
        $announcements = [
            ['title' => 'Q4 Townhall Meeting', 'content' => 'Join us for the quarterly update on Oct 28.', 'type' => 'event', 'priority' => 'high'],
            ['title' => 'New Health Policy', 'content' => 'Updated health insurance benefits for all employees.', 'type' => 'policy', 'priority' => 'medium'],
            ['title' => 'Office Maintenance', 'content' => 'Server room maintenance scheduled for this weekend.', 'type' => 'urgent', 'priority' => 'critical'],
        ];
        foreach ($announcements as $a) {
            Announcement::firstOrCreate(
                ['title' => $a['title']],
                array_merge($a, [
                    'created_by_id' => $admin->id,
                    'is_active' => true,
                    'start_date' => Carbon::now()->subDays(rand(0, 5)),
                ])
            );
        }

        // 4. Seed Jobs
        $siteId = Site::first()?->id ?? 1;
        $categoryId = JobCategory::firstOrCreate(['title' => 'General'], ['created_by' => $admin->id])->id;
        
        $jobTitles = ['Senior DevOps Engineer', 'UX Design Lead', 'Product Manager', 'Account Executive'];
        foreach ($jobTitles as $title) {
            Job::updateOrCreate(
                ['title' => $title],
                [
                    'description' => 'Exciting role in our growing team.',
                    'requirement' => 'Experience in relevant field.',
                    'branch' => $siteId,
                    'category' => $categoryId,
                    'status' => 'active',
                    'start_date' => Carbon::now()->subDays(rand(1, 30)),
                    'end_date' => Carbon::now()->addDays(30),
                    'created_by' => $admin->id,
                    'applicant' => rand(5, 50),
                    'code' => 'JOB' . rand(100, 999),
                    'skill' => 'PHP, Laravel, Vue',
                    'position' => 1,
                    'salary' => '50k - 80k',
                ]
            );
        }
        $jobs = Job::all();
        
        $stageTitles = ['Applied', 'Interviewing', 'Technical Test', 'Final Round', 'Offer'];
        foreach ($stageTitles as $s) {
            JobStage::firstOrCreate(['title' => $s], ['created_by' => $admin->id, 'order' => 0]);
        }
        $stages = JobStage::all();

        // 5. Seed Applications
        if (JobApplication::where('email', 'like', 'sarah.jenkins%')->count() == 0) {
            $candidateNames = ['Sarah Jenkins', 'Eleanor K.', 'James Wilson', 'Julia Roberts', 'Thomas Chen'];
            foreach ($candidateNames as $name) {
                JobApplication::create([
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)) . '@' . uniqid() . 'example.com',
                    'phone' => '12345678' . rand(10, 99),
                    'job' => $jobs->random()->id,
                    'stage' => $stages->random()->id,
                    'created_by' => $admin->id,
                    'created_at' => rand(0, 1) ? Carbon::now() : Carbon::now()->subDays(rand(1, 10)),
                    'address' => 'Sample Address',
                    'gender' => 'female',
                    'dob' => '1995-01-01',
                    'order' => 0,
                ]);
            }
        }
    }
}
