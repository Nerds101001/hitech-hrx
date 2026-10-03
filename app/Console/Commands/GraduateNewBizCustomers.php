<?php

namespace App\Console\Commands;

use App\Models\SalesPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The Sales Report's 5-month rule, run daily: a New Biz customer with sales in
 * 5 different months moves to CCare (SalesPipeline::graduateIfDue()). Entering
 * a sale on the Sales Report applies it at once; this catches the rest -
 * imports, Setu syncs, and anything entered before the rule existed.
 */
class GraduateNewBizCustomers extends Command
{
    protected $signature = 'sales:graduate-newbiz {--dry-run : List the customers that would move, change nothing}';

    protected $description = 'Move New Biz customers with sales in 5 different months to CCare (earlier months stay New Biz)';

    public function handle(): int
    {
        $dueIds = DB::table('sales_pipeline_months as m')
            ->join('sales_pipelines as sp', 'sp.id', '=', 'm.sales_pipeline_id')
            ->whereNull('sp.deleted_at')
            ->whereNotNull('sp.new_biz_id')
            ->where('m.sale_amount', '>', 0)
            ->groupBy('sp.id')
            ->havingRaw('COUNT(*) >= ?', [SalesPipeline::NEW_BIZ_MONTHS])
            ->pluck('sp.id');

        $moved = 0;
        $noCcare = 0;
        foreach (SalesPipeline::whereIn('id', $dueIds)->orderBy('id')->get() as $p) {
            if ($this->option('dry-run')) {
                $ccare = $p->ccare_id ?: SalesPipeline::mappedCcareFor($p->salesperson_id);
                $this->line(sprintf('  #%d %s -> %s', $p->id, $p->party_name, $ccare ? "CCare #$ccare" : 'NO CCare person mapped'));
                $ccare ? $moved++ : $noCcare++;
                continue;
            }
            if ($p->graduateIfDue()) {
                $moved++;
                $this->line(sprintf('  #%d %s -> CCare #%d from %s', $p->id, $p->party_name, $p->ccare_id, $p->ccare_from));
            } else {
                $noCcare++;
                $this->warn(sprintf('  #%d %s stays New Biz: no CCare person mapped to its salesperson', $p->id, $p->party_name));
            }
        }

        $this->info(($this->option('dry-run') ? 'Would move' : 'Moved') . " $moved customer(s) to CCare; $noCcare left in New Biz for want of a CCare person.");

        return self::SUCCESS;
    }
}
