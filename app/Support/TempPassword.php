<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The first password emailed to a new joiner.
 *
 * It used to be 'Hitech@' plus rand(1000, 9999): 9,000 possible values, all
 * sharing a known prefix, from a non-cryptographic generator - anyone who knew
 * a joiner's email could guess their way in before they did. This is 12
 * characters of letters and digits from a CSPRNG (Str::password uses
 * random_int). No symbols, so it can be read off an email and typed without
 * confusion.
 */
class TempPassword
{
    public static function make(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }
}
