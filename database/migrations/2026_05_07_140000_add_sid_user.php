<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        // Get a valid tenant_id from existing users
        $tenantId = DB::table('users')->value('tenant_id');

        // Get designation id for Admin or first designation
        $designationId = DB::table('designations')->where('name', 'like', '%Admin%')->value('id')
            ?? DB::table('designations')->value('id');

        // Get team id (first available)
        $teamId = DB::table('teams')->value('id');

        // Get shift id (first available)
        $shiftId = DB::table('shifts')->value('id');

        // Get leave policy profile id (first available)
        $leavePolicyProfileId = DB::table('leave_policy_profiles')->value('id');

        // Check if user already exists
        if (DB::table('users')->where('email', 'Sid@rustx.com')->exists()) {
            return;
        }

        $userId = DB::table('users')->insertGetId([
            'first_name'            => 'Sidharth',
            'last_name'             => 'Sareen',
            'email'                 => 'Sid@rustx.com',
            'password'              => Hash::make('HiTech@123'),
            'phone'                 => '9915715000',
            'designation_id'        => $designationId,
            'team_id'               => $teamId,
            'shift_id'              => $shiftId,
            'leave_policy_profile_id' => $leavePolicyProfileId,
            'attendance_type'       => 'open',
            'work_type'             => 'office',
            'status'                => 'active',
            'tenant_id'             => $tenantId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // Assign admin role via Spatie
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole && $userId) {
            $modelType = 'App\\Models\\User';
            DB::table('model_has_roles')->insert([
                'role_id'    => $adminRole->id,
                'model_type' => $modelType,
                'model_id'   => $userId,
            ]);
        }
    }

    public function down(): void
    {
        $user = DB::table('users')->where('email', 'Sid@rustx.com')->first();
        if ($user) {
            DB::table('model_has_roles')->where('model_id', $user->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        }
    }
};
