<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A reviewed mapping from a Tally salesperson ledger name to the HRX user it
 * refers to (or a confirmed exclusion, for ledger buckets/territories/brands
 * that are not people). See hrx:setu-mapping-report for how this is
 * populated and app/Services/SetuReportService.php for how it's consumed.
 */
class SetuSalespersonMapping extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $table = 'setu_salesperson_mappings';
    protected $fillable = ['tally_name', 'user_id', 'status', 'note', 'tenant_id'];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXCLUDED  = 'excluded';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
