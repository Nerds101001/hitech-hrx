<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $shift = Shift::first() ?? Shift::create([
            'name' => 'General Shift',
            'code' => 'GEN',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'start_date' => '2020-01-01',
            'status' => 'active'
        ]);

        $startDate = now()->subDays(30);
        $endDate = now();

        $this->command->info("Seeding attendance for " . $users->count() . " users from " . $startDate->toDateString() . " to " . $endDate->toDateString());

        foreach ($users as $user) {
            $currentTime = clone $startDate;
            
            while ($currentTime <= $endDate) {
                // Skip Sundays
                if ($currentTime->isSunday()) {
                    $currentTime->addDay();
                    continue;
                }

                // Randomly skip some days (90% attendance)
                if (rand(1, 100) > 90) {
                    $currentTime->addDay();
                    continue;
                }

                $checkIn = clone $currentTime;
                $checkIn->setTime(8, rand(45, 59), rand(0, 59)); // Check in between 8:45 and 8:59
                
                if (rand(1, 10) > 8) {
                    $checkIn->setTime(9, rand(1, 30), rand(0, 59)); // 20% chance of being slightly late
                }

                $checkOut = clone $currentTime;
                $checkOut->setTime(18, rand(0, 30), rand(0, 59)); // Check out between 18:00 and 18:30

                // Prevent future check-out for today
                if ($currentTime->isToday() && $checkOut->isFuture()) {
                    $checkOut = now();
                }

                // Create Attendance Record
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'shift_id' => $user->shift_id ?? $shift->id,
                    'site_id' => $user->site_id,
                    'check_in_time' => $checkIn,
                    'check_out_time' => $checkOut,
                    'status' => 'present',
                    'tenant_id' => $user->tenant_id ?: 1,
                    'created_at' => $checkIn,

                    'updated_at' => $checkOut,
                ]);


                // Create Logs
                AttendanceLog::create([
                    'attendance_id' => $attendance->id,
                    'type' => 'check_in',
                    'shift_id' => $attendance->shift_id,
                    'latitude' => 28.375, // Default CG Office lat
                    'longitude' => 76.960, // Default CG Office long
                    'tenant_id' => $user->tenant_id,
                    'created_at' => $checkIn,
                ]);

                AttendanceLog::create([
                    'attendance_id' => $attendance->id,
                    'type' => 'check_out',
                    'shift_id' => $attendance->shift_id,
                    'latitude' => 28.375,
                    'longitude' => 76.960,
                    'tenant_id' => $user->tenant_id,
                    'created_at' => $checkOut,
                ]);


                $currentTime->addDay();
            }
        }

        $this->command->info("Attendance seeding complete.");
    }
}
