<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;

class SalesPipeline extends Model
{
    use HasFactory, SoftDeletes, TenantTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'razor_blade'   => 'boolean',
        'upgrade'       => 'boolean',
        'rate_increase' => 'boolean',
        'is_locked'     => 'boolean',
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

    public function months()
    {
        return $this->hasMany(SalesPipelineMonth::class);
    }
}
