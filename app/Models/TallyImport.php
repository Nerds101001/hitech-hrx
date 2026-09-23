<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per `hrx:import-tally` run -- drives the "data as of" banner. */
class TallyImport extends Model
{
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
