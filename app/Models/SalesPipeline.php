<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalesPipeline extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, TenantTrait, Auditable;

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

    // Additional representatives — tagged users see this customer too.
    public function extraReps()
    {
        return $this->hasMany(SalesPipelineExtraRep::class);
    }
}
