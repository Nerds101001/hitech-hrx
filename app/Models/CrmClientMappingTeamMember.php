<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One person tagged on a customer's Internal Team, in one role. A role can
 * hold several of these (e.g. two New Biz reps) — see the
 * 2026_09_13_150000 migration for why this exists as its own table rather
 * than widening crm_client_mappings' single-FK columns.
 */
class CrmClientMappingTeamMember extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $table = 'crm_client_mapping_team';
    protected $fillable = ['crm_client_mapping_id', 'user_id', 'role', 'tenant_id'];

    public const ROLES = ['sales_person', 'ccare', 'new_biz', 'billing', 'finance', 'other'];

    public const ROLE_LABELS = [
        'sales_person' => 'Sales Person',
        'ccare' => 'CCare',
        'new_biz' => 'New Biz',
        'billing' => 'Billing',
        'finance' => 'Finance',
        'other' => 'Other',
    ];

    /** The matching single-FK column on crm_client_mappings this role mirrors,
     *  for the primary-person sync described in the migration. 'other' has no
     *  matching column — it's new, with nothing legacy to keep in sync. */
    public const PRIMARY_COLUMN = [
        'sales_person' => 'salesperson_id',
        'ccare' => 'ccare_id',
        'new_biz' => 'new_biz_id',
        'billing' => 'billing_id',
        'finance' => 'finance_id',
    ];

    public function mapping()
    {
        return $this->belongsTo(CrmClientMapping::class, 'crm_client_mapping_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
