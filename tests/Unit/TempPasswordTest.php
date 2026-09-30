<?php

namespace Tests\Unit;

use App\Support\TempPassword;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit M6): joiners were emailed 'Hitech@' plus four
 * digits - 9,000 guessable values with a known prefix.
 */
class TempPasswordTest extends TestCase
{
    public function test_it_is_long_mixed_and_not_the_old_guessable_pattern(): void
    {
        $seen = [];
        for ($i = 0; $i < 300; $i++) {
            $p = TempPassword::make();
            $this->assertSame(12, strlen($p));
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $p, 'letters and digits only, easy to type from an email');
            $this->assertMatchesRegularExpression('/[0-9]/', $p);
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $p);
            $this->assertStringStartsNotWith('Hitech@', $p);
            $seen[$p] = true;
        }
        $this->assertCount(300, $seen, 'no repeats');
    }
}
