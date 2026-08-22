<?php

namespace App\Models\Concerns;

use App\Enums\Status;
use Illuminate\Support\Facades\Cache;

/**
 * For small reference/lookup models (Department, Designation, Team, Site)
 * that back a dropdown on nearly every form but change only a few times a
 * year. cachedActive() replaces the identical where('status', ACTIVE)->get()
 * that used to be re-run on every request across ~20 call sites. The cache
 * is invalidated on any save/delete/restore, so an edit is visible
 * immediately -- there is no fixed TTL to wait out.
 */
trait HasCachedActiveList
{
    protected static function bootHasCachedActiveList(): void
    {
        static::saved(fn () => Cache::forget(static::activeListCacheKey()));
        static::deleted(fn () => Cache::forget(static::activeListCacheKey()));
        static::restored(fn () => Cache::forget(static::activeListCacheKey()));
    }

    protected static function activeListCacheKey(): string
    {
        return 'active_list:' . static::class;
    }

    /** All active rows, ordered by name. */
    public static function cachedActive()
    {
        return Cache::rememberForever(static::activeListCacheKey(), function () {
            return static::where('status', Status::ACTIVE)->orderBy('name')->get();
        });
    }
}
