<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** One row per `hrx:import-tally` run -- drives the "data as of" banner. */
class TallyImport extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function latestGood(): ?self
    {
        return static::whereIn('status', ['ok', 'partial'])->latest('id')->first();
    }
}
