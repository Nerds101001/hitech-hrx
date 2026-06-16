<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalespersonTarget extends Model
{
    use HasFactory, TenantTrait;

    protected $fillable = [
        'tenant_id',
        'salesperson_id',
        'month_year',
        'kingo_target',
        'bingo_target'
    ];

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }
}
