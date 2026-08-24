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

    public function ccare()
    {
        return $this->belongsTo(User::class, 'ccare_id');
    }

    public function newBiz()
    {
        return $this->belongsTo(User::class, 'new_biz_id');
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
