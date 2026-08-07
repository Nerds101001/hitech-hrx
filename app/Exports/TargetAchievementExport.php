<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The Target Achievement popup, as a spreadsheet.
 *
 * On screen a cell is coloured green / yellow / orange / red by how close the
 * actual sale is to that tier. A spreadsheet gets the same colours AND an
 * explicit "% of target" column per tier — colour alone cannot be filtered or
 * sorted, and this is the number people actually want to rank by.
 *
 * All figures are ₹ Lakh, written as numbers rather than formatted strings so
 * they still sum and sort.
 */
class TargetAchievementExport implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    /**
     * Row holding the column headings. A spacer row of [] is silently dropped
     * by the writer, which shifted every style down one row, so the title and
     * headings simply sit on rows 1 and 2 with no spacer.
     */
    private const HEADER_ROWS = 2;

    public function __construct(
        private array $rows,
        private string $monthLabel,
        private string $scopeLabel,
    ) {}

    public function title(): string
    {
        // Excel forbids : \ / ? * [ ] in sheet names and caps them at 31 chars.
        return mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $this->monthLabel) ?: 'Targets', 0, 31);
    }

    public function array(): array
    {
        $out = [
            ['Target Achievement — ' . $this->monthLabel . ($this->scopeLabel ? '  (' . $this->scopeLabel . ')' : '') . '   ·   all figures ₹ Lakh'],
            ['Salesperson', 'Actual Sale', 'Pending', 'Bingo', 'Bingo %', 'Kingo', 'Kingo %', 'Super Kingo', 'Super Kingo %', 'Highest tier achieved'],
        ];

        foreach ($this->rows as $r) {
            $actual = (float) ($r['actual'] ?? 0);
            $pct = fn ($t) => $t > 0 ? round($actual / $t * 100, 1) : null;

            $bingo = (float) ($r['bingo'] ?? 0);
            $kingo = (float) ($r['kingo'] ?? 0);
            $super = (float) ($r['super'] ?? 0);

            $highest = '—';
            if ($super > 0 && $actual >= $super)      $highest = 'Super Kingo';
            elseif ($kingo > 0 && $actual >= $kingo)  $highest = 'Kingo';
            elseif ($bingo > 0 && $actual >= $bingo)  $highest = 'Bingo';

            $out[] = [
                $r['name'] ?? '',
                $actual,
                (float) ($r['pending'] ?? 0),
                $bingo > 0 ? $bingo : null, $pct($bingo),
                $kingo > 0 ? $kingo : null, $pct($kingo),
                $super > 0 ? $super : null, $pct($super),
                $highest,
            ];
        }

        if (count($out) === self::HEADER_ROWS) {
            $out[] = ['No targets set for this month.'];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();
                $last  = $sheet->getHighestRow();
                $hdr   = self::HEADER_ROWS;

                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle("A{$hdr}:J{$hdr}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A{$hdr}:J{$hdr}")->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FF0F766E');
                $sheet->getStyle("A{$hdr}:J{$hdr}")->getAlignment()
                      ->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->freezePane('B' . ($hdr + 1));

                if ($last <= $hdr) return;

                $sheet->setAutoFilter("A{$hdr}:J{$last}");
                $sheet->getStyle("A{$hdr}:J{$last}")->getBorders()->getAllBorders()
                      ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D7DE');
                $sheet->getStyle('B' . ($hdr + 1) . ":I{$last}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Same thresholds as the popup, so screen and sheet agree.
                foreach (range($hdr + 1, $last) as $row) {
                    $actual = (float) $sheet->getCell('B' . $row)->getValue();
                    foreach (['D' => 'E', 'F' => 'G', 'H' => 'I'] as $tierCol => $pctCol) {
                        $target = $sheet->getCell($tierCol . $row)->getValue();
                        if ($target === null || $target === '' || (float) $target <= 0) continue;
                        $p = $actual / (float) $target;
                        $argb = $p >= 1 ? 'FF16A34A' : ($p >= 0.75 ? 'FFFDE047' : ($p >= 0.50 ? 'FFFB923C' : 'FFDC2626'));
                        $font = ($p >= 0.75 && $p < 1) ? 'FF000000' : 'FFFFFFFF';
                        foreach ([$tierCol, $pctCol] as $c) {
                            $sheet->getStyle($c . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                                  ->getStartColor()->setARGB($argb);
                            $sheet->getStyle($c . $row)->getFont()->getColor()->setARGB($font);
                        }
                    }
                }
            },
        ];
    }
}
