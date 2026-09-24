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
 * The fill-in sheet for one KPI, one month, one team.
 *
 * Column A carries the assignment id and is what the import matches on -- a
 * name can be spelled two ways or shared by two people, an id cannot. It is
 * locked and greyed so nobody edits it by accident, and any row whose id the
 * uploader is not entitled to fill is rejected on the way back in.
 */
class KpiBulkEntryExport implements FromArray, WithTitle, ShouldAutoSize, WithEvents
{
    private const HEADER_ROWS = 2;

    public function __construct(
        private $template,
        private \Carbon\Carbon $month,
        private $rows,
    ) {}

    public function title(): string
    {
        return mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $this->template->title) ?: 'KPI', 0, 31);
    }

    public function array(): array
    {
        $target = $this->template->target_value;

        $out = [
            [
                $this->template->title . '  —  ' . $this->month->format('F Y')
                . ($target ? '   (target ' . rtrim(rtrim(number_format((float) $target, 2), '0'), '.') . ')' : '')
                . '   ·   fill the VALUE column and upload this file back',
            ],
            ['ID (do not edit)', 'Employee', 'Department', 'VALUE', 'Note (optional)'],
        ];

        foreach ($this->rows as $a) {
            $out[] = [
                $a->id,
                trim(($a->user->first_name ?? '') . ' ' . ($a->user->last_name ?? '')),
                $a->user->department->name ?? '—',
                null,
                null,
            ];
        }

        if (count($out) === self::HEADER_ROWS) {
            $out[] = ['', 'Nobody is assigned this KPI.', '', '', ''];
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

                $sheet->mergeCells('A1:E1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)
                    ->getColor()->setARGB('FF1E3A5F');
                $sheet->getRowDimension(1)->setRowHeight(22);

                $sheet->getStyle("A{$hdr}:E{$hdr}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A{$hdr}:E{$hdr}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF0F766E');
                $sheet->getStyle("A{$hdr}:E{$hdr}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                if ($last <= $hdr) {
                    return;
                }

                $sheet->getStyle("A{$hdr}:E{$last}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D7DE');

                // The id column is reference data, not something to type in.
                $sheet->getStyle('A' . ($hdr + 1) . ":A{$last}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF1F5F9');
                $sheet->getStyle('A' . ($hdr + 1) . ":A{$last}")->getFont()->getColor()->setARGB('FF94A3B8');

                // The one column anyone should be typing in.
                $sheet->getStyle('D' . ($hdr + 1) . ":D{$last}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFEF9C3');
                $sheet->getStyle('D' . ($hdr + 1) . ":D{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . ($hdr + 1) . ":D{$last}")->getNumberFormat()->setFormatCode('#,##0.00');

                $sheet->freezePane('A' . ($hdr + 1));
            },
        ];
    }
}
