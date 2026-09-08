<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The Fill Targets grid, as a spreadsheet.
 *
 * Deliberately a faithful copy of the screen rather than a flat list: the
 * point of that page is reading a person's three targets against their three
 * live figures down one column, and flattening it to one row per person would
 * put nineteen columns of numbers on a single line and destroy that. So the
 * block structure survives -- six rows per salesperson, the same S. KINGO /
 * KINGO / BINGO / FCST / Sc. Fcst / Act. Sale order, and the same colours the
 * page uses to tell them apart.
 *
 * All figures are Rs Lakh, written as numbers rather than formatted strings so
 * they still sum and sort. An unset target is left genuinely empty rather than
 * carrying the page's em-dash placeholder, which would poison a SUM().
 */
class KingoBingoTargetsExport implements FromArray, WithTitle, WithEvents
{
    /** Title row + group header + column header. */
    private const HEADER_ROWS = 3;

    /** Rows in one salesperson block, including the trailing spacer. */
    private const BLOCK_ROWS = 7;

    /** Palette lifted from fill-targets.blade.php, as ARGB. */
    private const C_GRP_SP  = 'FF0F2848';
    private const C_GRP_ANN = 'FF92400E';
    private const C_GRP_QTR = 'FF1D4ED8';
    private const C_GRP_MON = 'FF1E3A5F';
    private const C_COL_ANN = 'FFB45309';
    private const C_COL_QTR = 'FF2563EB';
    private const C_COL_MON = 'FF1E3A5F';
    private const C_COL_CUR = 'FF1D4ED8';
    private const C_NAME_BG = 'FFF1F5F9';
    private const C_NAME_FG = 'FF1E3A5F';
    private const C_SEP     = 'FFF1F5F9';
    private const C_BORDER  = 'FFE5E7EB';
    private const C_WHITE   = 'FFFFFFFF';

    /**
     * The six rows of a block: label, target field (null means a read-only
     * live figure), live key, text colour, fill colour. Same order as the page.
     */
    private const ROWS = [
        ['S. KINGO',  'super_kingo_target', null,               'FF991B1B', 'FFFEF2F2'],
        ['KINGO',     'kingo_target',       null,               'FF3730A3', 'FFEEF2FF'],
        ['BINGO',     'bingo_target',       null,               'FF166534', 'FFF0FDF4'],
        ['FCST',      null,                 'forecast',         'FF1D4ED8', 'FFEFF6FF'],
        ['Sc. Fcst',  null,                 'forecast_service', 'FF7C3AED', 'FFF5F3FF'],
        ['Act. Sale', null,                 'actual',           'FF065F46', 'FFECFDF5'],
    ];

    public function __construct(private array $grid)
    {
    }

    public function title(): string
    {
        return 'FY ' . str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $this->grid['selectedFy']);
    }

    public function array(): array
    {
        $g        = $this->grid;
        $months   = $g['months'];
        $quarters = $g['quarterKeys'];
        $qLabels  = ['Q1 Apr-Jun', 'Q2 Jul-Sep', 'Q3 Oct-Dec', 'Q4 Jan-Mar'];
        $scope    = $g['scopeLabel'] ?? '';
        $width    = 3 + count($quarters) + count($months);

        $title = 'Kingo & Bingo Targets  -  FY ' . $g['selectedFy']
            . ($scope !== '' ? '   (' . $scope . ')' : '')
            . '   -   all figures Rs Lakh';

        $out = [array_pad([$title], $width, null)];

        // Group header, then column header. Merged into place in AfterSheet.
        $grp = ['Salesperson', '', 'Annual'];
        foreach ($quarters as $i => $q) {
            $grp[] = $i === 0 ? 'Quarterly' : '';
        }
        foreach ($months as $i => $m) {
            $grp[] = $i === 0 ? 'Monthly  Apr - Mar' : '';
        }
        $out[] = $grp;

        $col = ['', '', $g['selectedFy']];
        foreach ($quarters as $i => $q) {
            $col[] = $qLabels[$i] ?? ('Q' . ($i + 1));
        }
        foreach ($months as $m) {
            $col[] = $m['label'] . ($m['key'] === $g['currentMonthKey'] ? ' (now)' : '');
        }
        $out[] = $col;

        if ($g['salespersons']->isEmpty()) {
            $out[] = array_pad(['No salespersons match this filter.'], $width, null);

            return $out;
        }

        foreach ($g['salespersons'] as $sp) {
            $spT  = $g['targets'][$sp->id] ?? collect();
            $live = $g['liveByCsp'][$sp->id] ?? [];

            $target = function ($key, $field) use ($spT) {
                $v = optional($spT->firstWhere('month_year', $key))->{$field};

                return $v > 0 ? (float) $v : null;
            };

            foreach (self::ROWS as $i => [$label, $field, $liveKey, , ]) {
                // The name is written once and merged down the six rows.
                $line = [$i === 0 ? $sp->name : '', $label];

                if ($field !== null) {
                    $line[] = $target($g['annualKey'], $field);
                    foreach ($quarters as $q) {
                        $line[] = $target($q, $field);
                    }
                    foreach ($months as $m) {
                        $line[] = $target($m['key'], $field);
                    }
                } else {
                    // Live figures are monthly facts -- the annual and quarter
                    // cells stay blank, exactly as they do on the page.
                    $line[] = null;
                    foreach ($quarters as $q) {
                        $line[] = null;
                    }
                    foreach ($months as $m) {
                        $v = (float) ($live[$m['key']][$liveKey] ?? 0);
                        $line[] = $v > 0 ? $v : null;
                    }
                }

                $out[] = $line;
            }

            // Spacer, so the blocks read apart the way they do on screen.
            $out[] = array_fill(0, $width, null);
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet    = $e->sheet->getDelegate();
                $g        = $this->grid;
                $months   = $g['months'];
                $quarters = $g['quarterKeys'];

                $nQ    = count($quarters);
                $nM    = count($months);
                $width = 3 + $nQ + $nM;

                $lastC  = $this->col($width);
                $annC   = $this->col(3);
                $qFirst = $this->col(4);
                $qLast  = $this->col(3 + $nQ);
                $mFirst = $this->col(4 + $nQ);

                // ---- title ----
                $sheet->mergeCells("A1:{$lastC}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)
                    ->getColor()->setARGB(self::C_NAME_FG);
                $sheet->getRowDimension(1)->setRowHeight(22);

                // ---- group header (row 2) ----
                $sheet->mergeCells('A2:A3');
                $sheet->mergeCells('B2:B3');
                if ($nQ > 1) {
                    $sheet->mergeCells("{$qFirst}2:{$qLast}2");
                }
                if ($nM > 1) {
                    $sheet->mergeCells("{$mFirst}2:{$lastC}2");
                }

                $this->paint($sheet, 'A2:B3', self::C_GRP_SP, self::C_WHITE, true);
                $this->paint($sheet, "{$annC}2", self::C_GRP_ANN, self::C_WHITE, true);
                $this->paint($sheet, "{$qFirst}2:{$qLast}2", self::C_GRP_QTR, self::C_WHITE, true);
                $this->paint($sheet, "{$mFirst}2:{$lastC}2", self::C_GRP_MON, self::C_WHITE, true);

                // ---- column header (row 3) ----
                $this->paint($sheet, "{$annC}3", self::C_COL_ANN, self::C_WHITE, true);
                $this->paint($sheet, "{$qFirst}3:{$qLast}3", self::C_COL_QTR, self::C_WHITE, true);
                $this->paint($sheet, "{$mFirst}3:{$lastC}3", self::C_COL_MON, self::C_WHITE, true);

                // The current month keeps its brighter highlight.
                foreach ($months as $i => $m) {
                    if ($m['key'] !== $g['currentMonthKey']) {
                        continue;
                    }
                    $this->paint($sheet, $this->col(4 + $nQ + $i) . '3', self::C_COL_CUR, self::C_WHITE, true);
                }

                $sheet->getStyle("A2:{$lastC}3")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A2:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // ---- widths, freeze ----
                $sheet->getColumnDimension('A')->setWidth(26);
                $sheet->getColumnDimension('B')->setWidth(10);
                foreach (range(3, $width) as $c) {
                    $sheet->getColumnDimension($this->col($c))->setWidth(10.5);
                }
                $sheet->freezePane("{$annC}4");

                if ($g['salespersons']->isEmpty()) {
                    return;
                }

                // ---- one block per salesperson ----
                $row = self::HEADER_ROWS + 1;

                foreach ($g['salespersons'] as $sp) {
                    $first = $row;
                    $last  = $row + 5;

                    $sheet->mergeCells("A{$first}:A{$last}");
                    $this->paint($sheet, "A{$first}:A{$last}", self::C_NAME_BG, self::C_NAME_FG, true);
                    $sheet->getStyle("A{$first}")->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(true);

                    foreach (self::ROWS as $i => [, $field, , $fg, $bg]) {
                        $r = $first + $i;

                        // The label cell keeps its own tint on every row.
                        $this->paint($sheet, "B{$r}", $bg, $fg, true);

                        if ($field === null) {
                            // Live row: the whole month band carries the row colour.
                            $this->paint($sheet, "{$mFirst}{$r}:{$lastC}{$r}", $bg, $fg, true);
                            continue;
                        }

                        // Target row: band tints first, then the filled look.
                        $this->paint($sheet, "{$annC}{$r}", 'FFFEF3C7', 'FF78350F');
                        $this->paint($sheet, "{$qFirst}{$r}:{$qLast}{$r}", 'FFEFF6FF', 'FF1D4ED8');

                        foreach ($months as $mi => $m) {
                            if ($m['key'] === $g['currentMonthKey']) {
                                $this->paint($sheet, $this->col(4 + $nQ + $mi) . $r, 'FFDBEAFE', 'FF1D4ED8');
                            }
                        }

                        $this->markFilled($sheet, $r, $annC, 'FFFEF08A', 'FF78350F');
                        foreach (range(4, 3 + $nQ) as $c) {
                            $this->markFilled($sheet, $r, $this->col($c), 'FFDBEAFE', 'FF1D4ED8');
                        }
                        foreach (range(4 + $nQ, $width) as $c) {
                            $this->markFilled($sheet, $r, $this->col($c), 'FFF0FDF4', 'FF166534');
                        }
                    }

                    // Spacer row.
                    $sep = $last + 1;
                    $sheet->getRowDimension($sep)->setRowHeight(5);
                    $this->paint($sheet, "A{$sep}:{$lastC}{$sep}", self::C_SEP, self::C_SEP);

                    $row += self::BLOCK_ROWS;
                }

                $lastRow = $row - 1;

                $sheet->getStyle("A2:{$lastC}{$lastRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::C_BORDER);
                $sheet->getStyle("{$annC}4:{$lastC}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$annC}4:{$lastC}{$lastRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B4:B{$lastRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle("A4:{$lastC}{$lastRow}")->getFont()->setSize(9.5);

                $sheet->getPageSetup()->setOrientation('landscape')
                    ->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }

    /** A solid fill plus font colour, in one call. */
    private function paint($sheet, string $range, string $bg, string $fg, bool $bold = false): void
    {
        $style = $sheet->getStyle($range);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
        $style->getFont()->setBold($bold)->getColor()->setARGB($fg);
    }

    /** A target cell that actually holds a number gets the page's "filled" look. */
    private function markFilled($sheet, int $row, string $col, string $bg, string $fg): void
    {
        $v = $sheet->getCell($col . $row)->getValue();
        if ($v === null || $v === '' || (float) $v <= 0) {
            return;
        }
        $this->paint($sheet, $col . $row, $bg, $fg, true);
    }

    private function col(int $i): string
    {
        return Coordinate::stringFromColumnIndex($i);
    }
}
