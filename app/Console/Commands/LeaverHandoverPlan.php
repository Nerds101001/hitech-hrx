<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY. Lists, for every person who has left, what they still hold on the
 * sales side, and the month a handover would start from. Changes nothing.
 *
 * It exists so the replacements are chosen by people who know the business
 * before any handover is built or run: the output is the worksheet to fill in.
 *
 * Cut-off: the first day of the month after the last working day, but never a
 * month that has already started -- a closed month's credit is not rewritten.
 */
class LeaverHandoverPlan extends Command
{
    protected $signature = 'sales:leaver-handover-plan
                            {--csv= : also write the plan to this CSV file}
                            {--user= : only this user id}
                            {--all : also list leavers who hold nothing}';

    protected $description = 'Read-only: what each leaver still holds on the Sales Report, Client Master and CCare map';

    private const ROLE_COLS = ['salesperson_id' => 'Salesperson', 'ccare_id' => 'CCare', 'new_biz_id' => 'New Biz', 'new_biz_2_id' => 'New Biz 2'];

    public function handle(): int
    {
        $leavers = DB::table('users')
            ->whereNotIn('status', ['active', 'onboarding', 'onboarding_submitted'])
            ->when($this->option('user'), fn ($q, $u) => $q->where('id', $u))
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'status', 'exit_date', 'last_working_day', 'reporting_to_id', 'department_id']);

        $thisMonth = now()->format('Y-m');
        $rows = [];

        foreach ($leavers as $u) {
            $held = [];
            foreach (self::ROLE_COLS as $col => $label) {
                $held[$label] = DB::table('sales_pipelines')->whereNull('deleted_at')->where($col, $u->id)->count();
            }
            $extra = DB::table('sales_pipeline_extra_reps')->where('user_id', $u->id)->count();
            $crm = 0;
            if (Schema::hasTable('crm_client_mappings')) {
                foreach (['salesperson_id', 'ccare_id', 'new_biz_id', 'billing_id', 'finance_id'] as $c) {
                    $crm += DB::table('crm_client_mappings')->whereNull('deleted_at')->where($c, $u->id)->count();
                }
            }
            $mapAsSales = DB::table('cc_salesperson_map')->where('sales_user_id', $u->id)->count();
            $mapAsCc    = DB::table('cc_salesperson_map')->where('cc_user_id', $u->id)->count();
            $targets    = DB::table('salesperson_targets')->where('salesperson_id', $u->id)->where('month_year', '>=', $thisMonth)->count();

            $total = array_sum($held) + $extra + $crm + $mapAsSales + $mapAsCc + $targets;
            if ($total === 0 && !$this->option('all')) {
                continue;
            }

            $holdIds = DB::table('sales_pipelines')->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('salesperson_id', $u->id)->orWhere('ccare_id', $u->id)
                    ->orWhere('new_biz_id', $u->id)->orWhere('new_biz_2_id', $u->id))
                ->pluck('id');
            $lastSale = $holdIds->isEmpty() ? null : DB::table('sales_pipeline_months')
                ->whereIn('sales_pipeline_id', $holdIds)->where('sale_amount', '>', 0)->max('month_year');
            $recent = $holdIds->isEmpty() ? 0 : (float) DB::table('sales_pipeline_months')
                ->whereIn('sales_pipeline_id', $holdIds)
                ->where('month_year', '>=', Carbon::now()->subMonths(3)->format('Y-m'))->sum('sale_amount');

            $lwd = $u->last_working_day ?: $u->exit_date;
            $after = $lwd ? Carbon::parse($lwd)->startOfMonth()->addMonth()->format('Y-m') : null;
            $cutoff = $after ? max($after, $thisMonth) : $thisMonth;

            $mgr = $u->reporting_to_id
                ? DB::table('users')->where('id', $u->reporting_to_id)->where('status', 'active')->selectRaw("concat(first_name,' ',last_name) n")->value('n')
                : null;

            $rows[] = [
                'id'          => $u->id,
                'name'        => trim($u->first_name . ' ' . $u->last_name),
                'status'      => $u->status,
                'left'        => $lwd ? Carbon::parse($lwd)->toDateString() : '?',
                'cutoff'      => $cutoff,
                'salesperson' => $held['Salesperson'],
                'ccare'       => $held['CCare'],
                'new_biz'     => $held['New Biz'] + $held['New Biz 2'],
                'extra_rep'   => $extra,
                'client_mstr' => $crm,
                'cc_map'      => $mapAsSales + $mapAsCc,
                'targets'     => $targets,
                'last_sale'   => $lastSale ?: '-',
                'sale_3m_L'   => round($recent, 2),
                'manager'     => $mgr ?: '-',
            ];
        }

        $this->table(array_keys($rows[0] ?? ['none' => 1]), $rows);
        $this->info(count($rows) . ' of ' . $leavers->count() . ' leavers hold something. Nothing was changed.');
        $this->line('Sale rows still carrying sales in the last 3 months need attention first (sale_3m_L > 0).');

        if ($path = $this->option('csv')) {
            $fh = fopen($path, 'w');
            fputcsv($fh, array_merge(array_keys($rows[0] ?? ['none' => 1]), ['REPLACEMENT_USER_ID (fill in)']));
            foreach ($rows as $r) {
                fputcsv($fh, array_merge(array_values($r), ['']));
            }
            fclose($fh);
            $this->info("Written to $path");
        }

        return self::SUCCESS;
    }
}
