<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Holiday extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'holidays';

  protected $fillable = [
    'name',
    'date',
    'code',
    'notes',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
    'site_id',
  ];

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id');
  }

  protected $casts = [
    'date' => 'date',
    'status' => Status::class,
  ];

  /**
   * Holidays that apply to one site: the site's own plus every company-wide
   * one (site_id NULL). A holiday tagged to another location must never leak
   * — Punjab's Lohri is not a holiday for Chennai staff.
   *
   * Every consumer (attendance grid, payroll, comp-off, dashboards) must
   * scope through this or Holiday::appliesTo(); a bare Holiday::whereDate()
   * silently treats a location holiday as company-wide.
   */
  public function scopeForSite($query, $siteId)
  {
    return $query->where(function ($q) use ($siteId) {
      $q->whereNull('site_id');
      if ($siteId) {
        $q->orWhere('site_id', $siteId);
      }
    });
  }

  /** True when $date is a holiday for the given user (site-aware). */
  public static function appliesTo(?User $user, $date): bool
  {
    return static::query()
      ->forSite($user?->site_id)
      ->whereDate('date', $date)
      ->exists();
  }

  /**
   * ['Y-m-d' => name] of holidays applying to $user within a date range.
   * Keyed by date string so callers can do direct lookups.
   */
  public static function mapForUser(?User $user, $from, $to): array
  {
    return static::query()
      ->forSite($user?->site_id)
      ->whereBetween('date', [$from, $to])
      ->get(['date', 'name'])
      ->mapWithKeys(fn ($h) => [\Carbon\Carbon::parse($h->date)->toDateString() => $h->name])
      ->all();
  }
}
