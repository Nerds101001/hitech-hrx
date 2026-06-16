<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class SalesPipelineMonth extends Model
{
    use HasFactory, TenantTrait;

    protected $guarded = ['id'];

    public function pipeline()
    {
        return $this->belongsTo(SalesPipeline::class, 'sales_pipeline_id');
    }
}
