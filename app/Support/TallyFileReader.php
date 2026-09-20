<?php

namespace App\Support;

/**
 * Reads one of Tally's daily text exports: semicolon-separated, no header row,
 * UTF-16 with a BOM, Indian digit grouping ("1,14,558.00"), dates as "1-Jul-26",
 * negatives as "(-)1,234.00". The columns are positional, so the reader is
 * strict about the column count -- a layout change in the Tally export must
 * fail loudly here rather than silently shift every value one column over.
 */
class TallyFileReader
{
    /**
     * @return array{rows: array<int, array<int,string>>, glued: int, malformed: int, samples: array<int,string>, total: int}
     */
    public static function read(string $path, int $expectedColumns): array
    {
        $lines = preg_split('/\r\n|\n|\r/', self::decode((string) file_get_contents($path)));

        $rows = [];
        $glued = 0;
        $malformed = 0;
        $samples = [];
        $total = 0;
        $n = count($lines);

        for ($i = 0; $i < $n; $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $total++;
            $fields = explode(';', $lines[$i]);

            if (count($fields) !== $expectedColumns) {
                // A free-text field with line breaks in it (an item name, a PO
                // remark) arrives split across several physical lines. Glue the
                // following lines back on -- but only accept the result if it
                // lands on exactly the expected column count, and give up the
                // moment it overshoots, so a genuinely broken line can never
                // swallow the valid records after it (those would overshoot).
                $buffer = $lines[$i];
                $ok = false;
                for ($k = 1; $k <= 8 && $i + $k < $n; $k++) {
                    // A line that is already a complete record on its own is
                    // never a continuation. Without this, a stray fragment
                    // followed by a good record glues to it (1 + 4 - 1 fields
                    // = 4) and silently pollutes that record's first field.
                    if (count(explode(';', $lines[$i + $k])) >= $expectedColumns) {
                        break;
                    }
                    $buffer .= ' ' . $lines[$i + $k];
                    $candidate = explode(';', $buffer);
                    if (count($candidate) === $expectedColumns) {
                        $fields = $candidate;
                        $i += $k;
                        $glued++;
                        $ok = true;
                        break;
                    }
                    if (count($candidate) > $expectedColumns) {
                        break;
                    }
                }
                if (!$ok) {
                    $malformed++;
                    if (count($samples) < 3) {
                        $samples[] = mb_substr($lines[$i], 0, 140);
                    }
                    continue;
                }
            }

            $rows[] = array_map('trim', $fields);
        }

        return ['rows' => $rows, 'glued' => $glued, 'malformed' => $malformed, 'samples' => $samples, 'total' => $total];
    }

    private static function decode(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }
        // No BOM: UTF-16 text is full of NUL bytes, plain ANSI/UTF-8 is not.
        if (substr_count(substr($raw, 0, 400), "\x00") > 20) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    /** "1,14,558.00" -> 114558.0 ; "(-)1,234.00" -> -1234.0 ; null when it isn't a number at all. */
    public static function amount(?string $s): ?float
    {
        $s = trim((string) $s);
        if ($s === '') {
            return null;
        }
        $negative = str_starts_with($s, '(-)') || str_starts_with($s, '-');
        $digits = str_replace([',', '(-)', '-', ' '], '', $s);
        if (!preg_match('/^\d+(\.\d+)?$/', $digits)) {
            return null;
        }
        return $negative ? -((float) $digits) : (float) $digits;
    }

    /** "1-Jul-26" -> "2026-07-01" ; null when it isn't a date. */
    public static function date(?string $s): ?string
    {
        $d = \DateTime::createFromFormat('!j-M-y', trim((string) $s));
        return $d ? $d->format('Y-m-d') : null;
    }

    /** "210.00 Ltr." -> [210.0, "Ltr"] ; "1,500.000 No." -> [1500.0, "No"] ; "" -> [null, null]. */
    public static function quantity(?string $s): array
    {
        $s = trim((string) $s);
        if ($s === '' || !preg_match('/^([\d,]+(?:\.\d+)?)\s*(.*)$/', $s, $m)) {
            return [null, null];
        }
        $unit = rtrim(trim($m[2]), '.');
        return [(float) str_replace(',', '', $m[1]), $unit !== '' ? mb_substr($unit, 0, 20) : null];
    }

    /** "30 Days" -> 30 ; anything else -> null. */
    public static function creditDays(?string $s): ?int
    {
        return preg_match('/^(\d+)\s*Days?$/i', trim((string) $s), $m) ? (int) $m[1] : null;
    }

    /** "462.30/Ltr." -> [462.3, "Ltr"] ; "1,05,000.00/No." -> [105000.0, "No"] ; "" -> [null, null]. */
    public static function rate(?string $s): array
    {
        $s = trim((string) $s);
        if ($s === '' || !preg_match('/^([\d,]+(?:\.\d+)?)\s*(?:\/\s*(.*))?$/', $s, $m)) {
            return [null, null];
        }
        $unit = isset($m[2]) ? rtrim(trim($m[2]), '.') : '';
        return [(float) str_replace(',', '', $m[1]), $unit !== '' ? mb_substr($unit, 0, 20) : null];
    }

    /**
     * The invoice-number prefix, which is how Tally separates channels and plants:
     * "RMG2627-2018" -> RMG, "M992627-1460" -> M99, "TWTR-12345" -> TWTR.
     * Order ids that start with a digit (marketplace exports) have no series.
     */
    public static function series(?string $invoiceNo): string
    {
        $no = strtoupper(trim((string) $invoiceNo));
        if (preg_match('/^([A-Z]+(?:\d{2})?)\d{4}-\d+$/', $no, $m)) {   // RMG2627-2018, M992627-1460
            return $m[1];
        }
        if (preg_match('/^([A-Z]+)-\d+$/', $no, $m)) {                   // TWTR-12345, IN-1234
            return $m[1];
        }
        if (preg_match('/^([A-Z]+)/', $no, $m)) {                        // LWAA0M…, S99DV9…
            return mb_substr($m[1], 0, 6);
        }
        return 'Other';
    }
}
