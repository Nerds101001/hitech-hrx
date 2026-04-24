<?php

namespace App\Traits;

use App\Models\Tenant;

trait TenantTrait
{
  public function tenant()
  {
    return $this->belongsTo(Tenant::class);
  }


  protected static function bootTenantTrait(): void
  {
    // Apply Global Scope for strict tenant isolation matching standard multi-tenant patterns
    if (auth()->check()) {
      static::addGlobalScope('tenant', function ($builder) {
        $builder->where(function($q) {
            $q->where($q->getModel()->getTable() . '.tenant_id', auth()->user()->tenant_id)
              ->orWhereNull($q->getModel()->getTable() . '.tenant_id')
              ->orWhere($q->getModel()->getTable() . '.tenant_id', '');
        });
      });
    }

    static::creating(function ($model) {
      if (!$model->tenant_id && auth()->check()) {
        $model->tenant_id = auth()->user()->tenant_id;
      }
    });
  }
}
