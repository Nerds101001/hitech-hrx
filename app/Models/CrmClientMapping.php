<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CrmClientMapping extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, TenantTrait, Auditable;

    protected $table = 'crm_client_mappings';

    protected $fillable = [
        'tenant_id',
        'crm_company_code',
        'crm_company_name',
        'crm_city',
        'crm_state',
        'crm_stage',
        // Contact info from CRM
        'email',
        'phone',
        'address',
        'contact_person',
        // HRX employee assignments (all 5 roles)
        'salesperson_id',
        'ccare_id',
        'new_biz_id',
        'billing_id',
        'finance_id',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'crm_company_code' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\CrmPhoneMatcher::forget());
        static::deleted(fn () => \App\Support\CrmPhoneMatcher::forget());
        static::restored(fn () => \App\Support\CrmPhoneMatcher::forget());
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

    public function billing()
    {
        return $this->belongsTo(User::class, 'billing_id');
    }

    public function finance()
    {
        return $this->belongsTo(User::class, 'finance_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** The customer's own people -- their purchase team, finance team, etc. */
    public function contacts()
    {
        return $this->hasMany(CrmClientContact::class);
    }

    /** Everyone tagged on the Internal Team, any role, one or many per role. */
    public function teamMembers()
    {
        return $this->hasMany(CrmClientMappingTeamMember::class);
    }

    /** Same list, grouped by role — what the Customer 360 team card renders.
     *  email/phone are extra columns Customer Profile's quick mail/call icons
     *  use; the classic Customer 360 card simply doesn't read them. */
    public function teamMembersByRole()
    {
        return $this->teamMembers()->with('user:id,first_name,last_name,email,phone')->get()->groupBy('role');
    }

    /** The comment/call activity feed, newest first. Callers must paginate --
     *  this can accumulate one row per dispositioned call over an account's
     *  lifetime and must never be fetched in full. */
    public function activities()
    {
        return $this->hasMany(CrmClientActivity::class)->latest('id');
    }

    /** Sales Report rows reconciled to this CRM account (see the
     *  2026_08_22_100300 migration) -- Customer Profile's Sales & Revenue
     *  tab and sales-trend chart both read off this, not off party_name. */
    public function salesPipelines()
    {
        return $this->hasMany(SalesPipeline::class);
    }

    /** Every dialer-placed call linked to this account, newest first. */
    public function telephonyCalls()
    {
        return $this->hasMany(TelephonyCall::class)->latest('id');
    }

    /** Files attached to this account's record (Customer Profile's
     *  Documents tab). */
    public function documents()
    {
        return $this->hasMany(CrmClientDocument::class)->latest('id');
    }

    /**
     * Scope: get all mappings where a given user is assigned (any role).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('salesperson_id', $userId)
              ->orWhere('ccare_id', $userId)
              ->orWhere('new_biz_id', $userId)
              ->orWhere('billing_id', $userId)
              ->orWhere('finance_id', $userId);
        });
    }
}
