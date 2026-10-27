<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The canonical customer entity Sales Report rows link to via
 * `sales_pipelines.customer_id`. See the 2026_09_13_100000 migration for why
 * this exists — `party_name` stays free text on each row, deliberately
 * untouched; this is the thing that lets several spellings of the same
 * company share one identity.
 *
 * Auditable so a verify/rename has a "who and when" trail
 * (CustomerSetuMappingController is the only place that ever changes
 * setu_match_status away from 'none'/'similar').
 */
class Customer extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $fillable = [
        'canonical_name',
        'normalized_name',
        'setu_match_status',
        'setu_matched_name',
        'setu_verified_at',
        'setu_verified_by_id',
        'tally_match_status',
        'tally_matched_name',
        'tally_verified_at',
        'tally_verified_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'setu_verified_at' => 'datetime',
        'tally_verified_at' => 'datetime',
    ];

    public const STATUS_NONE = 'none';
    public const STATUS_SIMILAR = 'similar';
    public const STATUS_SUGGESTED = 'suggested';
    public const STATUS_VERIFIED = 'verified';

    public function salesPipelineRows()
    {
        return $this->hasMany(SalesPipeline::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'setu_verified_by_id');
    }

    public function tallyVerifiedBy()
    {
        return $this->belongsTo(User::class, 'tally_verified_by_id');
    }
}
