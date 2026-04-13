<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBase extends Model
{
    use TenantTrait, SoftDeletes;

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
