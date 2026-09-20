<?php

namespace App\Support;

class Fmt
{
    /** 1234567.5 -> "12,34,567.50" (Indian digit grouping, as Tally and the accounts team read it). */
    public static function inr(?float $value, int $decimals = 2): string
    {
        if ($value === null) {
            return '—';
        }
        $negative = $value < 0;
        [$int, $frac] = array_pad(explode('.', number_format(abs($value), $decimals, '.', '')), 2, '');
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
        }
        return ($negative ? '-' : '') . $rest . $last3 . ($frac !== '' ? '.' . $frac : '');
    }

    /** An amount already in Lakhs (Sales Report / Setu convention). */
    public static function lakh(?float $value, int $decimals = 2): string
    {
        return $value === null ? '—' : number_format($value, $decimals);
    }
}
