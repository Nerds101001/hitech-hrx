<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One role on one customer changing hands from a month on: from_user_id held it
 * before effective_month, to_user_id from then. See the migration for the rule
 * every report follows.
 *
 * hasAny() is the guard that keeps the whole feature inert until the first
 * handover: while the table is empty (or missing) callers skip all of it, so
 * existing behaviour and queries are unchanged.
 */
class SalesPipelineHandover extends Model
{
    protected $table = 'sales_pipeline_handovers';
    protected $guarded = ['id'];

    /** The four columns a customer's people sit in, plus the additional-rep tag. */
    public const ROLE_COLUMNS = ['salesperson_id', 'ccare_id', 'new_biz_id', 'new_biz_2_id'];
    public const EXTRA = 'extra';

    private static ?bool $any = null;
    /** @var array<int, array<int, object>> pipeline id => events, oldest first */
    private static array $byPipeline = [];
    /** @var array<int, array<int, bool>> pipeline id => user ids tagged as additional reps (only loaded for customers with a handover) */
    private static array $tags = [];

    /** Are there any live handovers at all? Cached for the request. */
    public static function hasAny(): bool
    {
        if (self::$any === null) {
            self::$any = Schema::hasTable('sales_pipeline_handovers')
                && DB::table('sales_pipeline_handovers')->whereNull('reverted_at')->exists();
        }

        return self::$any;
    }

    /** Forget what was cached (after a handover is applied or undone, and in tests). */
    public static function flush(): void
    {
        self::$any = null;
        self::$byPipeline = [];
        self::$tags = [];
    }

    /**
     * Live handovers on the given customers, oldest first.
     *
     * @param  iterable<int>  $pipelineIds
     * @return array<int, array<int, object>>  pipeline id => events
     */
    public static function eventsFor(iterable $pipelineIds): array
    {
        if (!self::hasAny()) {
            return [];
        }

        $want = [];
        foreach ($pipelineIds as $id) {
            if (!array_key_exists((int) $id, self::$byPipeline)) {
                $want[(int) $id] = true;
            }
        }
        foreach (array_chunk(array_keys($want), 1000) as $chunk) {
            foreach ($chunk as $id) {
                self::$byPipeline[$id] = [];
            }
            $rows = DB::table('sales_pipeline_handovers')->whereNull('reverted_at')
                ->whereIn('sales_pipeline_id', $chunk)
                ->orderBy('effective_month')->orderBy('id')
                ->get(['sales_pipeline_id', 'role', 'from_user_id', 'to_user_id', 'effective_month']);
            foreach ($rows as $r) {
                self::$byPipeline[(int) $r->sales_pipeline_id][] = $r;
            }
            $withEvents = [];
            foreach ($chunk as $id) {
                if (self::$byPipeline[$id]) {
                    $withEvents[] = $id;
                    self::$tags[$id] = [];
                }
            }
            if ($withEvents) {
                foreach (DB::table('sales_pipeline_extra_reps')->whereIn('sales_pipeline_id', $withEvents)->get(['sales_pipeline_id', 'user_id']) as $t) {
                    self::$tags[(int) $t->sales_pipeline_id][(int) $t->user_id] = true;
                }
            }
        }

        $out = [];
        foreach ($pipelineIds as $id) {
            $out[(int) $id] = self::$byPipeline[(int) $id] ?? [];
        }

        return $out;
    }

    /** Customers a person held before some handover took them over (they still count for the earlier months). */
    public static function pipelineIdsHeldBefore(array $userIds)
    {
        if (!self::hasAny() || !$userIds) {
            return collect();
        }

        return DB::table('sales_pipeline_handovers')->whereNull('reverted_at')
            ->whereIn('from_user_id', $userIds)->pluck('sales_pipeline_id')->unique()->values();
    }

    /**
     * Who held $column in month $mk: the previous holder of the earliest handover
     * that starts AFTER the month, otherwise the person in the column now.
     *
     * @param  array<int, object>  $events  this customer's events (see eventsFor)
     */
    public static function holderAt(?int $current, string $column, string $mk, array $events): ?int
    {
        foreach ($events as $e) {
            if ($e->role === $column && $e->effective_month > $mk) {
                return (int) $e->from_user_id;
            }
        }

        return $current ? (int) $current : null;
    }

    /** Is this person tagged as an additional rep on the customer? Call after eventsFor() has loaded it. */
    public static function isTagged(int $pipelineId, int $userId): bool
    {
        return isset(self::$tags[$pipelineId][$userId]);
    }

    /** Has this person's additional-rep tag on the customer ended by month $mk? */
    public static function extraTagEnded(int $userId, string $mk, array $events): bool
    {
        foreach ($events as $e) {
            if ($e->role === self::EXTRA && (int) $e->from_user_id === $userId && $e->effective_month <= $mk) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same rule in SQL: the holder of $column on customer alias $sp in the month of
     * $m.month_year. Use it where a query groups or joins on a role column.
     */
    public static function holderSql(string $column, string $sp = 'sp', string $m = 'm'): string
    {
        if (!in_array($column, self::ROLE_COLUMNS, true)) {
            throw new \InvalidArgumentException("not a role column: $column");
        }
        if (!self::hasAny()) {
            return "$sp.$column";
        }

        return "COALESCE((SELECT h.from_user_id FROM sales_pipeline_handovers h"
            . " WHERE h.sales_pipeline_id = $sp.id AND h.role = '$column' AND h.reverted_at IS NULL"
            . " AND h.effective_month > $m.month_year ORDER BY h.effective_month ASC, h.id ASC LIMIT 1), $sp.$column)";
    }
}
