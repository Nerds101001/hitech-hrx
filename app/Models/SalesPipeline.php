<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalesPipeline extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, TenantTrait, Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'razor_blade'   => 'boolean',
        'upgrade'       => 'boolean',
        'rate_increase' => 'boolean',
        'is_locked'     => 'boolean',
    ];

    /**
     * Static reason store for the audit trail — mirrors LeaveBalance's pattern
     * exactly (the only other model in this codebase that does this). Set
     * immediately before save() on the same instance; consumed once then reset.
     */
    public static ?string $auditReason = null;

    /** Sales in this many different months move a New Biz customer to CCare. */
    public const NEW_BIZ_MONTHS = 5;

    protected static function booted(): void
    {
        // New Biz -> CCare keeps the earlier months under New Biz. Switching
        // re-labelled the customer's whole history: every month it sold as a
        // New Biz customer moved to CCare, in the Category totals and in both
        // people's numbers. Every route that takes the New Biz person off a row
        // (Category dropdown, picking a CCare rep, claiming, the 5-month rule)
        // saves through here, so the boundary is recorded once.
        static::saving(function (SalesPipeline $p) {
            if (!$p->exists || !$p->isDirty('new_biz_id')) {
                return;
            }
            $was = $p->getOriginal('new_biz_id');
            if ($was && !$p->new_biz_id) {
                if (!$p->isDirty('ccare_from')) {
                    $p->ccare_from = now()->format('Y-m');
                }
                $p->former_new_biz_id = $was;
            } elseif ($p->new_biz_id) {
                // Back under New Biz: the whole customer is New Biz again.
                $p->ccare_from = null;
                $p->former_new_biz_id = null;
            }
        });
    }

    /** Does month $mk (YYYY-MM) of this customer count as New Biz? */
    public function isNewBizMonth(string $mk): bool
    {
        return $this->new_biz_id || ($this->ccare_from && $mk < $this->ccare_from);
    }

    /**
     * Is month $mk of this customer credited to $userId? Only a customer that
     * moved New Biz -> CCare splits: its New Biz person gets the months before
     * the move, its CCare person the months from it. Everyone else on the row
     * (salesperson, New Biz 2, extra reps) keeps every month, as before.
     */
    public function creditsMonth(int $userId, string $mk): bool
    {
        return self::creditsSide($this, $userId, (bool) ($this->ccare_from && $mk < $this->ccare_from));
    }

    /** creditsMonth() for a plain DB row; $beforeMove = the month is before ccare_from. */
    public static function creditsSide(object $row, int $userId, bool $beforeMove): bool
    {
        if (empty($row->ccare_from)) {
            return true;
        }
        $is = fn (string $col) => (int) ($row->{$col} ?? 0) === $userId;
        if ($is('salesperson_id') || $is('new_biz_id') || $is('new_biz_2_id')) {
            return true;
        }
        $cc = $is('ccare_id');
        $nb = $is('former_new_biz_id');
        if (!$cc && !$nb) {
            return true;
        }

        return $beforeMove ? $nb : $cc;
    }

    /**
     * creditsMonth() in SQL: keep only the month rows credited to $userId.
     * $m is the months table, or its alias in $monthQuery.
     */
    public static function whereMonthCredited($monthQuery, int $userId, string $m = 'sales_pipeline_months'): void
    {
        $monthQuery->whereExists(function ($e) use ($userId, $m) {
            $e->selectRaw('1')->from('sales_pipelines as cf')
              ->whereColumn('cf.id', "$m.sales_pipeline_id")
              ->where(function ($w) use ($userId, $m) {
                  $w->whereNull('cf.ccare_from')
                    ->orWhere('cf.salesperson_id', $userId)
                    ->orWhere('cf.new_biz_id', $userId)
                    ->orWhere('cf.new_biz_2_id', $userId)
                    ->orWhere(fn ($x) => $x->where('cf.ccare_id', $userId)->whereColumn("$m.month_year", '>=', 'cf.ccare_from'))
                    ->orWhere(fn ($x) => $x->where('cf.former_new_biz_id', $userId)->whereColumn("$m.month_year", '<', 'cf.ccare_from'))
                    ->orWhere(fn ($x) => $x->where(fn ($y) => $y->whereNull('cf.ccare_id')->orWhere('cf.ccare_id', '<>', $userId))
                                           ->where(fn ($y) => $y->whereNull('cf.former_new_biz_id')->orWhere('cf.former_new_biz_id', '<>', $userId)));
              });
        });
    }

    /** Keep only the month rows that count under $category: 'New Biz', 'CCare' or 'None'. */
    public static function whereMonthInCategory($monthQuery, string $category, string $m = 'sales_pipeline_months'): void
    {
        $monthQuery->whereExists(function ($e) use ($category, $m) {
            $e->selectRaw('1')->from('sales_pipelines as cg')->whereColumn('cg.id', "$m.sales_pipeline_id");
            if ($category === 'New Biz') {
                $e->where(fn ($w) => $w->whereNotNull('cg.new_biz_id')->orWhereColumn("$m.month_year", '<', 'cg.ccare_from'));
            } else {
                $e->whereNull('cg.new_biz_id')
                  ->where(fn ($w) => $w->whereNull('cg.ccare_from')->orWhereColumn("$m.month_year", '>=', 'cg.ccare_from'));
            }
        });
    }

    /** The CCare person mapped to a salesperson (Customer Care mapping), if any. */
    public static function mappedCcareFor($salespersonId): ?int
    {
        if (!$salespersonId) {
            return null;
        }
        $maps = CcSalespersonMap::where('sales_user_id', $salespersonId)->with(['ccUser.department'])->get();
        foreach ($maps as $map) {
            if (!$map->ccUser) continue;
            if ($map->role === 'ccare') return $map->ccUser->id;
            if ($map->role) continue;
            if ($map->ccUser->department) {
                $dept = strtolower($map->ccUser->department->name);
                if (str_contains($dept, 'customer care') || str_contains($dept, 'c-care') || str_contains($dept, 'ccare')) {
                    return $map->ccUser->id;
                }
            }
        }

        return null;
    }

    /**
     * The 5-month rule: a New Biz customer with sales in NEW_BIZ_MONTHS
     * different months moves to CCare, to the CCare person mapped to its
     * salesperson, from the month after the one that completed it - never
     * earlier than this month, so a closed month is never re-labelled.
     * Returns true when it moved.
     */
    public function graduateIfDue(): bool
    {
        if (!$this->new_biz_id || $this->trashed()) {
            return false;
        }
        $saleMonths = $this->months()->where('sale_amount', '>', 0)->orderBy('month_year')->pluck('month_year');
        if ($saleMonths->count() < self::NEW_BIZ_MONTHS) {
            return false;
        }
        $ccareId = $this->ccare_id ?: self::mappedCcareFor($this->salesperson_id);
        if (!$ccareId) {
            return false;   // nobody to hand it to: stays New Biz until the mapping exists
        }

        $after = \Carbon\Carbon::parse($saleMonths[self::NEW_BIZ_MONTHS - 1] . '-01')->addMonth()->format('Y-m');
        $this->ccare_from = max($after, now()->format('Y-m'));
        $this->ccare_id = $ccareId;
        $this->new_biz_id = null;
        self::$auditReason = 'Moved to CCare: sales in ' . self::NEW_BIZ_MONTHS . ' months (CCare from ' . $this->ccare_from . ')';
        $this->save();   // the saving hook records former_new_biz_id

        return true;
    }

    public function transformAudit(array $data): array
    {
        if (static::$auditReason) {
            $data['user_agent'] = static::$auditReason;
            static::$auditReason = null;
        }
        return $data;
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    /** The canonical customer this row's party_name has been linked to, if any. */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function ccare()
    {
        return $this->belongsTo(User::class, 'ccare_id');
    }

    public function newBiz()
    {
        return $this->belongsTo(User::class, 'new_biz_id');
    }

    /** New Biz person of a customer that moved to CCare, for the months before ccare_from. */
    public function formerNewBiz()
    {
        return $this->belongsTo(User::class, 'former_new_biz_id');
    }

    // Exclusive third owner slot, independent of ccare_id/new_biz_id — used by
    // the customer search-and-claim feature. Not the "New BIZ2" grid column
    // (that's the extraReps tag list below, a different concept).
    public function newBiz2()
    {
        return $this->belongsTo(User::class, 'new_biz_2_id');
    }

    public function months()
    {
        return $this->hasMany(SalesPipelineMonth::class);
    }

    // Additional representatives — tagged users see this customer too.
    // "Other Reps" holds CCare / New Biz people; "Service Sales" holds
    // salespersons. Both live in sales_pipeline_extra_reps, split by `type`.
    public function extraReps()
    {
        return $this->hasMany(SalesPipelineExtraRep::class)
                    ->where('type', SalesPipelineExtraRep::TYPE_OTHER);
    }

    public function serviceReps()
    {
        return $this->hasMany(SalesPipelineExtraRep::class)
                    ->where('type', SalesPipelineExtraRep::TYPE_SERVICE);
    }

    // Every tag regardless of column (used where both need loading at once).
    public function allExtraReps()
    {
        return $this->hasMany(SalesPipelineExtraRep::class);
    }

    // The CRM.API-synced customer this row was reconciled against, if any —
    // see SalesPipelineController::syncCrmMapping()/resolveCrmMapping().
    public function crmClientMapping()
    {
        return $this->belongsTo(CrmClientMapping::class);
    }
}
