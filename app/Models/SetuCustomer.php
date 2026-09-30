<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Local cache of one Setu (Tally) customer, kept fresh by
 * hrx:sync-setu-customers. See the 2026_09_13_100002 migration docblock for
 * why this cache exists instead of querying Setu live per request.
 */
class SetuCustomer extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'name',
        'normalized_name',
        'recent_months',
        'recent_total',
        'last_synced_at',
    ];

    protected $casts = [
        'recent_months' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
