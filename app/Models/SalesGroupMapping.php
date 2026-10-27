<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A reviewed mapping from Tally's own `salesperson` tag
 * (tally_invoices.salesperson — a per-voucher Cost Centre value Tally
 * itself assigns) to the HRX user it refers to (or a confirmed exclusion,
 * for values that are accounting buckets, not a salesperson). Populated by
 * hrx:sales-group-mapping-sync; see TallyReportService for how it's
 * consumed. Supersedes SetuSalespersonMapping's role for sales reconciliation.
 */
class SalesGroupMapping extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $table = 'sales_group_mappings';
    protected $fillable = ['tally_salesperson', 'user_id', 'status', 'note', 'tenant_id'];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXCLUDED  = 'excluded';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
