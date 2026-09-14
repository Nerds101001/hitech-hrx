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

  /** A standard working day in minutes — the baseline a concession reduces. */
  public const FULL_DAY_MINUTES = 465;   // 7h45m, matches the attendance grid
  public const HALF_DAY_MINUTES = 240;   // 4h, the existing half-day threshold

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
    'department_id',
    'holiday_type',
    'hours_off',
    'session',
    'applies_to_gender',
  ];

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id');
  }

  public function department()
  {
    return $this->belongsTo(Department::class, 'department_id');
  }

  protected $casts = [
    'date' => 'date',
    'status' => Status::class,
    'hours_off' => 'float',
  ];

  /** True only for a whole-day holiday — the day is not worked at all. */
  public function isFullDay(): bool
  {
    return ($this->holiday_type ?? 'full') === 'full';
  }

  /** True for a half-day or hours-off concession — still a working day. */
  public function isPartDay(): bool
  {
    return !$this->isFullDay();
  }

  /** Does this concession apply to this employee (gender-scoped ones)? */
  public function appliesToUser(?User $user): bool
  {
    $scope = $this->applies_to_gender ?? 'all';
    if ($scope === 'all' || !$user) {
      return true;
    }
    $gender = $user->gender instanceof \App\Enums\Gender
      ? $user->gender->value
      : (string) $user->gender;

    return strtolower($gender) === $scope;
  }

  /** Minutes of concession this holiday grants. */
  public function concessionMinutes(): int
  {
    return match ($this->holiday_type ?? 'full') {
      'full'  => self::FULL_DAY_MINUTES,
      'half'  => (int) round(self::FULL_DAY_MINUTES / 2),
      'hours' => (int) round(((float) ($this->hours_off ?? 0)) * 60),
      default => 0,
    };
  }

  /** Short label for the UI, e.g. "2h off (morning)". */
  public function getTypeLabelAttribute(): string
  {
    return match ($this->holiday_type ?? 'full') {
      'full'  => 'Full Day',
      'half'  => 'Half Day' . ($this->session ? ' (' . ucfirst($this->session) . ')' : ''),
      'hours' => rtrim(rtrim(number_format((float) $this->hours_off, 2, '.', ''), '0'), '.')
                 . 'h off' . ($this->session ? ' (' . ucfirst($this->session) . ')' : ''),
      default => 'Full Day',
    };
  }

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

  /**
   * Holidays that reach this specific employee: matching on BOTH location and
   * department, where NULL on a column means "not restricted by that". So a
   * Production-only holiday skips Sales, and a Mumbai holiday skips Chennai,
   * while a row with both NULL reaches everyone.
   *
   * Gender scoping is applied in PHP by appliesToUser(), because it depends on
   * the employee's own record rather than the query.
   */
  public function scopeForUser($query, ?User $user)
  {
    return $query
      ->where(function ($q) use ($user) {
        $q->whereNull('site_id');
        if ($user?->site_id) {
          $q->orWhere('site_id', $user->site_id);
        }
      })
      ->where(function ($q) use ($user) {
        $q->whereNull('department_id');
        if ($user?->department_id) {
          $q->orWhere('department_id', $user->department_id);
        }
      });
  }

  /**
   * True when $date is a FULL-DAY holiday for this user (site- and
   * gender-aware). Part-day concessions are deliberately excluded: those days
   * are still worked, so they must not suppress attendance or earn a comp-off.
   */
  public static function appliesTo(?User $user, $date): bool
  {
    return static::query()
      ->forUser($user)
      ->whereDate('date', $date)
      ->where('holiday_type', 'full')
      ->get()
      ->contains(fn ($h) => $h->appliesToUser($user));
  }

  /**
   * The part-day concession in force for this user on $date, or null.
   * When several apply, the most generous one wins.
   */
  public static function partDayFor(?User $user, $date): ?self
  {
    return static::query()
      ->forUser($user)
      ->whereDate('date', $date)
      ->whereIn('holiday_type', ['half', 'hours'])
      ->get()
      ->filter(fn ($h) => $h->appliesToUser($user))
      ->sortByDesc(fn ($h) => $h->concessionMinutes())
      ->first();
  }

  /**
   * Minutes this user must actually work on $date to count as fully present,
   * after any part-day concession. A 2-hour morning concession turns the
   * standard 465-minute day into 345; a 4-hour one into 225.
   *
   * A 2-hour floor stops an oversized concession from letting a token
   * appearance count as a full day, while still honouring genuine grants
   * (the old half-day floor swallowed a legitimate 4-hour concession).
   */
  public const MIN_PRESENCE_MINUTES = 120;

  public static function requiredMinutesFor(?User $user, $date, int $standard = self::FULL_DAY_MINUTES): int
  {
    $c = static::partDayFor($user, $date);
    if (!$c) {
      return $standard;
    }

    return max(self::MIN_PRESENCE_MINUTES, $standard - $c->concessionMinutes());
  }

  /**
   * Up to $limit holidays from today onward that apply to this user — the
   * single source for every dashboard's "Upcoming Calendar" widget
   * (admin/hr/manager/employee all shared this one unscoped query before,
   * which is why a Sales-West-only holiday like Ganesh Chaturthi showed up
   * for every role regardless of their own site). Every holiday type is
   * included (full/half/hours), matching what those widgets showed before
   * this was scoped — only WHO it reaches changes, not WHAT.
   */
  public static function upcomingForUser(?User $user, int $limit = 5)
  {
    return static::query()
      ->forUser($user)
      ->where('date', '>=', now()->toDateString())
      ->orderBy('date')
      ->get()
      ->filter(fn ($h) => $h->appliesToUser($user))
      ->take($limit)
      ->values();
  }

  /** Count of this user's holidays (any type) falling in the current calendar month — the "Holidays This Month" tile. */
  public static function countThisMonthForUser(?User $user): int
  {
    return static::query()
      ->forUser($user)
      ->whereMonth('date', now()->month)
      ->whereYear('date', now()->year)
      ->get()
      ->filter(fn ($h) => $h->appliesToUser($user))
      ->count();
  }

  /**
   * ['Y-m-d' => name] of holidays applying to $user within a date range.
   * Keyed by date string so callers can do direct lookups.
   */
  public static function mapForUser(?User $user, $from, $to): array
  {
    return static::query()
      ->forUser($user)
      ->where('holiday_type', 'full')   // part-days are worked, not days off
      ->whereBetween('date', [$from, $to])
      ->get()
      ->filter(fn ($h) => $h->appliesToUser($user))
      ->mapWithKeys(fn ($h) => [\Carbon\Carbon::parse($h->date)->toDateString() => $h->name])
      ->all();
  }

  protected static function booted()
  {
    static::saved(function ($holiday) {
      // Only a full-day holiday can earn a comp-off (appliesTo() already
      // excludes part-day concessions from crediting), and only creating one
      // or moving its date can newly affect attendance already on the books.
      if ($holiday->holiday_type !== 'full') {
        return;
      }
      if (!$holiday->wasRecentlyCreated && !$holiday->wasChanged('date')) {
        return;
      }
      if (!$holiday->date) {
        return;
      }

      // Re-save every attendance record already on this date so Attendance's
      // own saved-hook (Sunday/Holiday/OFF-Saturday comp-off credit) picks
      // them up now that this holiday exists here — covers a holiday added
      // for a backdated day after attendance was already marked, and an
      // existing holiday's date being corrected. Each row's own hook still
      // decides eligibility per-user via Holiday::appliesTo(), so site- and
      // department-scoped holidays are unaffected by this being unscoped.
      $date = \Carbon\Carbon::parse($holiday->date)->toDateString();

      \App\Models\Attendance::whereDate('check_in_time', $date)
        ->whereNotNull('check_out_time')
        ->get()
        ->each(fn ($attendance) => $attendance->save());
    });
  }
}
