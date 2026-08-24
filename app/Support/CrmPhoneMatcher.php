<?php

namespace App\Support;

use App\Models\CrmClientContact;
use App\Models\CrmClientMapping;
use Illuminate\Support\Facades\Cache;

/**
 * Best-effort "which customer does this phone number belong to" lookup, used
 * to auto-link a telephony call that wasn't explicitly tagged to a customer
 * when it was placed (an inbound call, or one raised by the webhook receiver
 * for a call HRX never initiated). Matches against both a mapping's own
 * phone and any of its contacts' phones -- deliberately exact
 * (post-normalization), never fuzzy: a wrong auto-link would misattribute a
 * real conversation to the wrong company, which is worse than leaving it
 * unlinked for a human to fix.
 *
 * Runs on every dispositioned call and every webhook event, so the
 * phone -> mapping index is cached rather than queried fresh each time
 * (same reasoning as Models\Concerns\HasCachedActiveList) -- invalidated
 * from CrmClientMapping/CrmClientContact's own save/delete/restore hooks.
 */
class CrmPhoneMatcher
{
    private const CACHE_KEY = 'crm_phone_index';

    /** Strips everything but digits, then drops a leading 91 (India country
     *  code) or a single leading 0 (STD trunk prefix) so numbers stored in
     *  different conventions still compare equal. */
    public static function normalize(?string $number): ?string
    {
        if (!$number) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $number);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '91') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) > 10) {
            $digits = ltrim($digits, '0');
        }
        return $digits;
    }

    /** Forget the cached index -- called from the two models' save/delete/restore hooks. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** normalized phone => crm_client_mapping_id, built once and cached forever. */
    private static function index(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $index = [];
            foreach (CrmClientMapping::whereNotNull('phone')->get(['id', 'phone']) as $m) {
                if ($norm = self::normalize($m->phone)) {
                    $index[$norm] ??= $m->id;
                }
            }
            foreach (CrmClientContact::whereNotNull('phone')->get(['phone', 'crm_client_mapping_id']) as $c) {
                if ($norm = self::normalize($c->phone)) {
                    $index[$norm] ??= $c->crm_client_mapping_id;
                }
            }
            return $index;
        });
    }

    /**
     * Resolve a crm_client_mapping_id from either side of a call. Tries the
     * "to" number first (the number actually dialled is the stronger signal
     * for an outbound call), then "from". Returns null on no match.
     */
    public static function resolve(?string $fromNumber, ?string $toNumber): ?int
    {
        $index = self::index();
        foreach ([$toNumber, $fromNumber] as $candidate) {
            $norm = self::normalize($candidate);
            if ($norm && isset($index[$norm])) {
                return $index[$norm];
            }
        }
        return null;
    }
}
