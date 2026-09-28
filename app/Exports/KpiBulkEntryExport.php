<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The fill-in sheet: one row per person, one column per KPI.
 *
 * A matrix rather than a sheet per KPI, because that is the shape the numbers
 * arrive in -- one report listing everyone's calls, quotations and upsells
 * side by side. Choose one KPI and it is simply a one-column matrix.
 *
 * Column A carries the USER id and is what the import matches rows on; a name
 * can be spelled two ways or shared by two people, an id cannot. The KPI
 * columns are matched by their header text, so a reordered or partly deleted
 * sheet still lands in the right KPI.
 */
class KpiBulkEntryExport implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    private const HEADER_ROWS = 2;
    private const FIXED_COLS = 3;      // id, employee, department

    /** @param array $labels template id => column header (see KpiBulkEntryController::labelsFor) */
    public function __construct(
        private $templates,
        private \Carbon\Carbon $month,
        private $rows,
        private array $labels = [],
    ) {}

    private function label($t): string
    {
        return $this->labels[$t->id] ?? $t->title;
    }

    public function title(): string
    {
        $t = $this->templates->count() === 1 ? $this->label($this->templates->first()) : 'KPI Entry';

        return mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $t) ?: 'KPI', 0, 31);
    }

    public function array(): array
    {
        $intro = $this->month->format('F Y') . '   ·   fill the shaded columns and upload this file back'
            . '   ·   do not edit column A or the header row';

        $header = ['User ID (do not edit)', 'Employee', 'Department'];
        foreach ($this->templates as $t) {
            // The header text is how the import knows which KPI a column is --
            // hence the department on a title two KPIs share.
            $header[] = $this->label($t);
        }

        $out = [
            array_pad([$intro], count($header), null),
            $header,
        ];

        foreach ($this->rows as $r) {
            $line = [$r->user->id, $r->name, $r->department];
            foreach ($this->templates as $t) {
                $cell = $r->cells[$t->id] ?? null;
                // Pre-fill what is already recorded, so this doubles as a
                // correction sheet rather than only a blank form.
                $line[] = ($cell && $cell->value !== null) ? (float) $cell->value : null;
            }
            $out[] = $line;
        }

        if (count($out) === self::HEADER_ROWS) {
            $out[] = array_pad(['', 'Nobody is assigned the KPIs you chose.'], count($header), null);
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();
                $last = $sheet->getHighestRow();
                $hdr = self::HEADER_ROWS;
                $cols = self::FIXED_COLS + $this->templates->count();
                $lastCol = Coordinate::stringFromColumnIndex($cols);

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF1E3A5F');
                $sheet->getRowDimension(1)->setRowHeight(22);

                $sheet->getStyle("A{$hdr}:{$lastCol}{$hdr}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A{$hdr}:{$lastCol}{$hdr}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF0F766E');
                $sheet->getStyle("A{$hdr}:{$lastCol}{$hdr}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension($hdr)->setRowHeight(30);

                if ($last <= $hdr) {
                    return;
                }

                $sheet->getStyle("A{$hdr}:{$lastCol}{$last}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D7DE');

                // Reference data, not something to type in.
                $sheet->getStyle('A' . ($hdr + 1) . ":A{$last}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF1F5F9');
                $sheet->getStyle('A' . ($hdr + 1) . ":A{$last}")->getFont()->getColor()->setARGB('FF94A3B8');

                // Every KPI column is one to type in.
                for ($c = self::FIXED_COLS + 1; $c <= $cols; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $range = $col . ($hdr + 1) . ':' . $col . $last;
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEF9C3');
                    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getColumnDimension($col)->setWidth(16);
                }

                $sheet->freezePane('D' . ($hdr + 1));
            },
        ];
    }
}
