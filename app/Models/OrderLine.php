<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLine extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'order_lines';

  protected $fillable = [
    'order_id',
    'product_id',
    'quantity',
    'price',
    'total',
    'discount',
    'tax',
    'notes',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function order()
  {
    return $this->belongsTo(ProductOrder::class, 'order_id');
  }

  public function product()
  {
    return $this->belongsTo(Product::class, 'product_id');
  }

  protected $casts = [
    'price' => 'float',
    'total' => 'float',
    'discount' => 'float',
    'tax' => 'float',
    'quantity' => 'integer',
  ];
}
