<?php

namespace App\Models\Telephony;

use Illuminate\Database\Eloquent\Model;

class TelephonyReconciliationRun extends Model
{
  protected $table = 'telephony_reconciliation_runs';
  protected $guarded = ['id'];

  /**
   * period_date is deliberately NOT cast to a date.
   *
   * The daily run is looked up with updateOrCreate(['period_date' => 'Y-m-d']).
   * A date cast turns that into a Carbon on write and stores
   * "2026-08-19 00:00:00", so the next day's lookup for "2026-08-19" finds
   * nothing, tries to INSERT, and dies on the unique index - meaning a second
   * reconcile run on the same day would crash. Keeping it a plain string makes
   * the write and the lookup identical.
   */
  protected $casts = [
    'period_start' => 'datetime',
    'period_end' => 'datetime',
    'missing_call_uids' => 'array',
  ];
}
