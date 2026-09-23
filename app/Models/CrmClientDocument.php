<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CrmClientDocument extends Model implements AuditableContract
{
    use TenantTrait, SoftDeletes, Auditable;

    protected $fillable = [
        'crm_client_mapping_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
        'uploaded_by_id',
        'tenant_id',
    ];

    public function crmClientMapping()
    {
        return $this->belongsTo(CrmClientMapping::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
