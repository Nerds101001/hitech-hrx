<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalesPipelineMonth extends Model implements AuditableContract
{
    use HasFactory, TenantTrait, Auditable;

    protected $guarded = ['id'];

    public function pipeline()
    {
        return $this->belongsTo(SalesPipeline::class, 'sales_pipeline_id');
    }
}
