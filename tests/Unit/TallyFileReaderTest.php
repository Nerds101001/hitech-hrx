<?php

namespace Tests\Unit;

use App\Support\TallyFileReader;
use PHPUnit\Framework\TestCase;

/**
 * The Tally export has no header row and positional columns, so the reader is
 * the only thing standing between a changed layout and silently wrong money.
 * Pure functions and temp files -- deliberately NOT Tests\TestCase, so it never
 * boots the app or touches a database.
 */
class TallyFileReaderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
    }

    /** Writes $lines as a UTF-16LE file with a BOM, the way Tally does. */
    private function utf16File(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tally');
        file_put_contents($path, "\xFF\xFE" . mb_convert_encoding(implode("\r\n", $lines) . "\r\n", 'UTF-16LE', 'UTF-8'));
        return $this->files[] = $path;
    }

    public function test_amounts_use_indian_grouping_and_tally_negatives(): void
    {
        $this->assertSame(114558.0, TallyFileReader::amount('1,14,558.00'));
        $this->assertSame(0.0, TallyFileReader::amount('0.00'));
        $this->assertSame(-1234.5, TallyFileReader::amount('(-)1,234.50'));
        $this->assertNull(TallyFileReader::amount(''));
        $this->assertNull(TallyFileReader::amount('30 Days'));
    }

    public function test_dates_parse_day_month_year_and_reject_anything_else(): void
    {
        $this->assertSame('2026-07-01', TallyFileReader::date('1-Jul-26'));
        $this->assertSame('2025-12-31', TallyFileReader::date('31-Dec-25'));
        $this->assertNull(TallyFileReader::date('2026-07-01'));
        $this->assertNull(TallyFileReader::date(''));
    }

    public function test_quantity_splits_number_and_unit(): void
    {
        $this->assertSame([210.0, 'Ltr'], TallyFileReader::quantity('210.00 Ltr.'));
        $this->assertSame([1500.0, 'No'], TallyFileReader::quantity('1,500.000 No.'));
        $this->assertSame([20.0, 'Roll'], TallyFileReader::quantity('20 Roll'));
        $this->assertSame([null, null], TallyFileReader::quantity(''));
    }

    public function test_rate_splits_number_and_per_unit(): void
    {
        $this->assertSame([462.3, 'Ltr'], TallyFileReader::rate('462.30/Ltr.'));
        $this->assertSame([105000.0, 'No'], TallyFileReader::rate('1,05,000.00/No.'));
        $this->assertSame([100.0, null], TallyFileReader::rate('100.00'));
        $this->assertSame([null, null], TallyFileReader::rate(''));
        $this->assertSame([null, null], TallyFileReader::rate('n/a'));
    }

    public function test_invoice_series_is_the_prefix_before_the_year_and_serial(): void
    {
        $this->assertSame('RMG', TallyFileReader::series('RMG2627-2018'));
        $this->assertSame('M99', TallyFileReader::series('M992627-1460'));
        $this->assertSame('LDH', TallyFileReader::series('LDH2627-0474'));
        $this->assertSame('TWTR', TallyFileReader::series('TWTR-12345'));
        $this->assertSame('VMBR', TallyFileReader::series('VMBR-999'));
        $this->assertSame('LWAA', TallyFileReader::series('LWAA0M1234567890'));
        $this->assertSame('Other', TallyFileReader::series('402-1234567-1234567_1_SALE386'));
        $this->assertSame('Other', TallyFileReader::series(''));
    }

    public function test_credit_days(): void
    {
        $this->assertSame(30, TallyFileReader::creditDays('30 Days'));
        $this->assertNull(TallyFileReader::creditDays(''));
        $this->assertNull(TallyFileReader::creditDays('30-Nov-26'));
    }

    public function test_reads_utf16_and_repairs_a_record_wrapped_across_lines(): void
    {
        $path = $this->utf16File([
            'a; 1-Jul-26; INV-1; 100.00',
            'b; 2-Jul-26; item with a',            // field broken by a line break...
            'line break; 5.00',                    // ...continues here: b; date; "item with a line break"; 5.00
            'c; 3-Jul-26; INV-3; 300.00',
        ]);

        $r = TallyFileReader::read($path, 4);

        $this->assertCount(3, $r['rows']);
        $this->assertSame(1, $r['glued']);
        $this->assertSame(0, $r['malformed']);
        $this->assertSame('item with a line break', $r['rows'][1][2]);
        $this->assertSame('INV-3', $r['rows'][2][2]);
    }

    public function test_a_broken_line_is_dropped_without_swallowing_the_valid_record_after_it(): void
    {
        $path = $this->utf16File([
            'a; 1-Jul-26; INV-1; 100.00',
            'orphan fragment',                     // 1 column, nothing to glue it to
            'c; 3-Jul-26; INV-3; 300.00',          // a full record: must survive intact
        ]);

        $r = TallyFileReader::read($path, 4);

        $this->assertCount(2, $r['rows']);
        $this->assertSame(1, $r['malformed']);
        $this->assertSame('INV-3', $r['rows'][1][2]);
        $this->assertSame('orphan fragment', $r['samples'][0]);
    }

    public function test_a_shifted_layout_is_rejected_line_by_line_rather_than_read_wrongly(): void
    {
        // Tally added a column: every line now has 5 fields where 4 are expected.
        $path = $this->utf16File(['a; 1-Jul-26; INV-1; 100.00; extra', 'b; 2-Jul-26; INV-2; 200.00; extra']);

        $r = TallyFileReader::read($path, 4);

        $this->assertCount(0, $r['rows']);
        $this->assertSame(2, $r['malformed']);
    }

    public function test_plain_utf8_without_a_bom_still_reads(): void
    {
        $path = $this->files[] = tempnam(sys_get_temp_dir(), 'tally');
        file_put_contents($path, "a; 1-Jul-26; Café Ltd; 100.00\n");

        $r = TallyFileReader::read($path, 4);

        $this->assertSame('Café Ltd', $r['rows'][0][2]);
    }
}
