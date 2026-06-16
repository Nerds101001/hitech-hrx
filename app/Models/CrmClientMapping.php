<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;

class CrmClientMapping extends Model
{
    use HasFactory, SoftDeletes, TenantTrait;

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
