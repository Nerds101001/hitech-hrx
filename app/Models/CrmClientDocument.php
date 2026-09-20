<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmClientDocument extends Model
{
    use TenantTrait, SoftDeletes;

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
