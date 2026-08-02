<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Additional representative on a customer (beyond the primary
 * salesperson/ccare/newbiz columns). The tagged user sees the customer in
 * their own login. Fully audited so admin can trace who tagged whom.
 */
class SalesPipelineExtraRep extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $table = 'sales_pipeline_extra_reps';
    protected $fillable = ['sales_pipeline_id', 'user_id', 'type', 'tenant_id'];

    /** Tag lists sharing this table. */
    public const TYPE_OTHER   = 'other';   // CCare / New Biz people
    public const TYPE_SERVICE = 'service'; // salespersons (Service Sales column)

    public function pipeline()
    {
        return $this->belongsTo(SalesPipeline::class, 'sales_pipeline_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
