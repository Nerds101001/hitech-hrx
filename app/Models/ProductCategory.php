<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ProductCategory extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'product_categories';

  protected $fillable = [
    'name',
    'code',
    'description',
    'parent_id',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function parent()
  {
    return $this->belongsTo(ProductCategory::class, 'parent_id');
  }

  public function subCategories()
  {
    return $this->hasMany(ProductCategory::class, 'parent_id');
  }

  public function isActive(): bool
  {
    return $this->status == 'active';
  }
}
