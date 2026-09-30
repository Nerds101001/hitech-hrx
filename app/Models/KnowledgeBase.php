<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class KnowledgeBase extends Model implements AuditableContract
{
    use TenantTrait, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'title',
        'category',
        'content',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];
}
