<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports "Holiday List - Staff 2026.xlsx" (7 location blocks) into the
 * holidays table, mapped onto sites.
 *
 * Only FULL-DAY holidays become holiday rows — a holiday row marks a whole
 * day off in attendance and payroll, so the part-day arrangements in the
 * sheet's OFF TIME column (early off, late start, female-staff-only) would
 * wrongly zero out a working day. Those are recorded as notes on the row
 * where the same date is also a holiday elsewhere, and otherwise skipped and
 * reported, until real part-day support exists.
 *
 * Idempotent: keyed on (date, site_id, name), so re-running never duplicates.
 */
class ImportHolidayList2026Seeder extends Seeder
{
    /** Excel location block => site names it applies to */
    private const SITE_MAP = [
        'PUNJAB'        => ['Ramgarh', 'Sahnewal'],
        'MAHARASHTRA'   => ['Mumbai', 'Sales- West'],
        'SOUTH'         => ['Chennai', 'sales South'],
        'MANESAR STAFF' => ['Manesar 18/6', 'Manesar 99'],
        'AHMEDABAD'     => ['Ahmedabad'],
        'SALE TEAM'     => ['Sales', 'SALES NORTH', 'Sales East'],
        'CG STAFF'      => ['CG Office'],
    ];

    public function run(): void
    {
        $data = [
            'PUNJAB' => [
                ['2026-01-13', 'Lohri', true, 'HOLIDAY'],
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-03-04', 'Holi', true, 'HOLIDAY'],
                ['2026-04-14', 'Vaisakhi', false, '4 PM OFF'],
                ['2026-08-15', 'Independance Day', true, 'HOLIDAY'],
                ['2026-08-28', 'Raksha Bandhan', false, 'MORNING OFFICE TIMING 11:30 AM'],
                ['2026-09-04', 'Janam Ashtmi', false, '4:00 PM OFF'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-20', 'Dusshera', true, 'HOLIDAY'],
                ['2026-10-29', 'Karwa Chauth', false, 'FEMALE STAFF - 2PM OFF'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
                ['2026-11-11', 'Bhai Dooj', false, 'MORNING OFFICE TIMING 11.30 AM'],
                ['2026-11-24', 'Guru Nank Jayanti', false, '4 PM OFF'],
            ],
            'MAHARASHTRA' => [
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-03-04', 'Holi', true, 'HOLIDAY'],
                ['2026-05-01', 'Labour Day', true, 'HOLIDAY'],
                ['2026-08-28', 'Raksha Bandhan', false, 'MORNING OFFICE TIMING 11:30 AM'],
                ['2026-09-04', 'Janam Ashtmi', false, '4:00 PM OFF'],
                ['2026-09-14', 'Ganesh Chaturthi', true, 'HOLIDAY'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-20', 'Dusshera', true, 'HOLIDAY'],
                ['2026-10-29', 'Karwa Chauth', false, 'FEMALE STAFF - 2PM OFF'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
                ['2026-11-11', 'Bhai Dooj', false, 'MORNING OFFICE TIMING 11.30 AM'],
            ],
            'SOUTH' => [
                ['2026-01-15', 'Pongal', true, 'HOLIDAY'],
                ['2026-01-16', 'Pongal', true, 'HOLIDAY'],
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-04-14', 'Tamil New Year', true, 'HOLIDAY'],
                ['2026-08-15', 'Independance Day', true, 'HOLIDAY'],
                ['2026-09-14', 'Ganesh Chaturthi', true, 'HOLIDAY'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-19', 'Ayudhya Pooja', true, 'HOLIDAY'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
            ],
            'MANESAR STAFF' => [
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-03-04', 'Holi', true, 'HOLIDAY'],
                ['2026-08-15', 'Independance Day', true, 'HOLIDAY'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-20', 'Dusshera', true, 'HOLIDAY'],
                ['2026-10-29', 'Karwa Chauth', false, 'FEMALE STAFF - 2PM OFF'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
                ['2026-08-28', 'Raksha Bandhan', false, 'MORNING OFFICE TIMING 11:30 AM'],
                ['2026-11-11', 'Bhai Dooj', false, 'MORNING OFFICE TIMING 11.30 AM'],
            ],
            'AHMEDABAD' => [
                ['2026-01-14', 'Makkar Sakranti', false, '4 PM OFF'],
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-03-04', 'Holi', true, 'HOLIDAY'],
                ['2026-08-28', 'Raksha Bandhan', false, 'MORNING OFFICE TIMING 11:30 AM'],
                ['2026-09-04', 'Janam Ashtmi', false, '4:00 PM OFF'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-20', 'Dusshera', true, 'HOLIDAY'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
                ['2026-11-11', 'Bhai Dooj', false, 'MORNING OFFICE TIMING 11.30 AM'],
            ],
            'SALE TEAM' => [
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-03-04', 'Holi', true, 'HOLIDAY'],
                ['2026-08-15', 'Independance Day', true, 'HOLIDAY'],
                ['2026-08-28', 'Raksha Bandhan', false, 'MORNING OFFICE TIMING 11:30 AM'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-20', 'Dusshera', true, 'HOLIDAY'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
                ['2026-11-11', 'Bhai Dooj', false, 'MORNING OFFICE TIMING 11.30 AM'],
            ],
            'CG STAFF' => [
                ['2026-01-26', 'Republic Day', true, 'HOLIDAY'],
                ['2026-03-04', 'Holi', true, 'HOLIDAY'],
                ['2026-08-15', 'Independance Day', true, 'HOLIDAY'],
                ['2026-08-28', 'Raksha Bandhan', true, 'HOLIDAY'],
                ['2026-10-02', 'Gandhi Jayanti', true, 'HOLIDAY'],
                ['2026-10-20', 'Dusshera', true, 'HOLIDAY'],
                ['2026-10-29', 'Karwa Chauth', false, 'FEMALE STAFF - 2PM OFF'],
                ['2026-11-08', 'Diwali', true, 'HOLIDAY'],
                ['2026-11-09', 'Vishwakarma', true, 'HOLIDAY'],
                ['2026-11-11', 'Bhai Dooj', false, 'MORNING OFFICE TIMING 11.30 AM'],
                ['2026-12-25', 'Christmas', true, 'HOLIDAY'],
            ],
        ];

        // Resolve site names -> ids (duplicate names in `sites` all resolve, so a
        // holiday lands on every record sharing that name)
        $siteIdsByName = Site::all(['id', 'name'])
            ->groupBy(fn ($s) => strtolower(trim($s->name)))
            ->map(fn ($g) => $g->pluck('id')->all());

        $created = 0; $skippedExisting = 0; $partDay = []; $unmappedSites = [];
        $nextCode = (int) (Holiday::withTrashed()->max('id') ?? 0);

        foreach ($data as $block => $rows) {
            $siteNames = self::SITE_MAP[$block] ?? [];
            $siteIds = [];
            foreach ($siteNames as $name) {
                $ids = $siteIdsByName[strtolower(trim($name))] ?? [];
                if (empty($ids)) {
                    $unmappedSites[] = "{$block} -> {$name}";
                    continue;
                }
                $siteIds = array_merge($siteIds, $ids);
            }
            $siteIds = array_values(array_unique($siteIds));

            foreach ($rows as [$date, $name, $isFull, $offText]) {
                if (!$isFull) {
                    $partDay[] = "{$block} | {$date} | {$name} | {$offText}";
                    continue;
                }

                foreach ($siteIds as $siteId) {
                    $exists = Holiday::withTrashed()
                        ->whereDate('date', $date)
                        ->where('site_id', $siteId)
                        ->where('name', $name)
                        ->exists();

                    if ($exists) { $skippedExisting++; continue; }

                    $nextCode++;
                    Holiday::create([
                        'name'     => $name,
                        'date'     => $date,
                        'code'     => 'HOL' . str_pad((string) $nextCode, 4, '0', STR_PAD_LEFT),
                        'site_id'  => $siteId,
                        'notes'    => "Imported from Staff Holiday List 2026 ({$block})",
                        'status'   => 'active',
                    ]);
                    $created++;
                }
            }
        }

        $this->command->info("Holidays created      : {$created}");
        $this->command->info("Already present, kept : {$skippedExisting}");

        if ($unmappedSites) {
            $this->command->warn("\nSite names not found in `sites` (their holidays were skipped):");
            foreach (array_unique($unmappedSites) as $u) { $this->command->warn("  - {$u}"); }
        }

        $this->command->warn("\n" . count($partDay) . " PART-DAY rows deliberately NOT imported as holidays");
        $this->command->warn("(a holiday row = full day off, which would wrongly cancel a working day):");
        foreach ($partDay as $p) { $this->command->line("  - {$p}"); }
    }
}
