<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Product extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'products';

  protected $fillable = [
    'name',
    'description',
    'product_code',
    'status',
    'category_id',
    'base_price',
    'discount',
    'tax',
    'price',
    'stock',
    'images',
    'thumbnail',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function category()
  {
    return $this->belongsTo(ProductCategory::class, 'category_id');
  }

  public function orderLines()
  {
    return $this->hasMany(OrderLine::class, 'product_id');
  }

  protected $casts = [
    'images' => 'array',
    'price' => 'float',
    'base_price' => 'float',
    'discount' => 'float',
    'tax' => 'float',
    'stock' => 'integer'
  ];
}
