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
    // Apply Global Scope for tenant isolation. Rows with NULL tenant_id are
    // shared/global records (this install predates tenant_id backfill), so
    // they stay visible to every tenant; strict equality alone hides them
    // and breaks leave types, holidays, sites and departments app-wide.
    if (auth()->check()) {
      static::addGlobalScope('tenant', function ($builder) {
        $table = $builder->getModel()->getTable();
        $builder->where(function ($q) use ($table) {
          $q->where($table . '.tenant_id', auth()->user()->tenant_id)
            ->orWhereNull($table . '.tenant_id');
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
