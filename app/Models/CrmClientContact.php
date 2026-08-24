<?php

namespace App\Models;

use App\Enums\CrmContactRole;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CrmClientContact extends Model implements AuditableContract
{
    use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

    protected $table = 'crm_client_contacts';

    protected $fillable = [
        'crm_client_mapping_id',
        'name',
        'role',
        'email',
        'phone',
        'is_primary',
        'notes',
        'created_by_id',
        'updated_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'role' => CrmContactRole::class,
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\CrmPhoneMatcher::forget());
        static::deleted(fn () => \App\Support\CrmPhoneMatcher::forget());
        static::restored(fn () => \App\Support\CrmPhoneMatcher::forget());
    }

    public function crmClientMapping()
    {
        return $this->belongsTo(CrmClientMapping::class);
    }
}
