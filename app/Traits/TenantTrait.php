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
    // Apply Global Scope for tenant isolation. Rows with an UNSET tenant_id are
    // shared/global records (this install predates tenant_id backfill), so
    // they stay visible to every tenant; strict equality alone hides them
    // and breaks leave types, holidays, sites and departments app-wide.
    //
    // "Unset" means NULL *or* empty string: the backfill left a mix of both
    // (e.g. all 5 teams, 7 departments, 3 shifts and 3 sites carry ''), and
    // every user row carries NULL. Matching only NULL made those '' rows
    // invisible to every logged-in user — departments and shifts silently
    // rendered as "—" on profiles and dashboards.
    /* ── Tenant scoping disabled 2026-08-01 ──────────────────────────────
     * This install is single-company: there is NO `tenants` table, and every
     * row in every one of the 115 tenant_id columns is NULL or ''. The scope
     * below therefore matched EVERY row on EVERY query — it filtered nothing,
     * while still costing a wrapped WHERE on each one and forcing 57
     * withoutGlobalScopes() work-arounds across the app.
     *
     * It had already been patched twice for exactly this reason (strict
     * equality hid leave types, holidays, sites and departments app-wide).
     *
     * Disabling it is behaviour-neutral: row counts before and after are
     * identical. The tenant_id columns and the auto-fill hook below are left
     * intact so this can be reinstated if real multi-tenancy is ever built —
     * at which point it needs a tenants table, a backfill and its own tests.
     *
     * To restore: git revert, or un-comment the block below.
     * ------------------------------------------------------------------
     * if (auth()->check()) {
     *   static::addGlobalScope('tenant', function ($builder) {
     *     $table = $builder->getModel()->getTable();
     *     $builder->where(function ($q) use ($table) {
     *       $q->where($table . '.tenant_id', auth()->user()->tenant_id)
     *         ->orWhereNull($table . '.tenant_id')
     *         ->orWhere($table . '.tenant_id', '');
     *     });
     *   });
     * }
     */

    static::creating(function ($model) {
      if (!$model->tenant_id && auth()->check()) {
        $model->tenant_id = auth()->user()->tenant_id;
      }
    });
  }
}
