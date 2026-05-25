<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckLeaveProfiles extends Command
{
    protected $signature = 'leave:check-profiles';
    protected $description = 'Show all leave policy profiles with their rules';

    public function handle()
    {
        $profiles = DB::table('leave_policy_profiles')
            ->whereNull('deleted_at')
            ->get();

        if ($profiles->isEmpty()) {
            $this->warn('No leave policy profiles found.');
            return;
        }

        $this->info("Total Profiles: " . $profiles->count());
        $this->line('');

        foreach ($profiles as $profile) {
            $userCount = DB::table('users')
                ->where('leave_policy_profile_id', $profile->id)
                ->whereNull('deleted_at')
                ->count();

            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Profile: [{$profile->id}] {$profile->name}");
            $this->line("  Description : " . ($profile->description ?? 'N/A'));
            $this->line("  Status      : " . ($profile->status ?? 'active'));
            $this->line("  Employees   : {$userCount} assigned");

            // Saturday config
            $satConfig = json_decode($profile->saturday_off_config ?? '[]', true);
            if (!empty($satConfig)) {
                $this->line("  Saturday Off: " . implode(', ', $satConfig));
            }

            // Rules
            $rules = DB::table('leave_policy_profile_rules as r')
                ->join('leave_types as lt', 'lt.id', '=', 'r.leave_type_id')
                ->where('r.profile_id', $profile->id)
                ->select('lt.name', 'lt.code', 'lt.is_paid', 'r.*')
                ->get();

            if ($rules->isEmpty()) {
                $this->warn("  No rules defined.");
            } else {
                $rows = [];
                foreach ($rules as $rule) {
                    $rows[] = [
                        $rule->code,
                        $rule->name,
                        $rule->is_paid ? 'Paid' : 'Unpaid',
                        $rule->max_per_month ?? '-',
                        $rule->max_per_year ?? '-',
                        $rule->is_carry_forward ? 'Yes (max ' . ($rule->carry_forward_max_days ?? '?') . ')' : 'No',
                        $rule->max_consecutive_days ?? '-',
                        $rule->tenure_required_months ? $rule->tenure_required_months . ' months' : '-',
                    ];
                }
                $this->table(
                    ['Code', 'Leave Type', 'Paid?', '/Month', '/Year', 'Carry Fwd', 'Max Consec.', 'Tenure Req.'],
                    $rows
                );
            }

            $this->line('');
        }
    }
}
