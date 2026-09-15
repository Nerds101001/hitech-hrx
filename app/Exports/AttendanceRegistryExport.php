<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AttendanceRegistryExport implements FromArray, WithTitle, WithHeadings, WithColumnWidths, WithEvents
{
    private array $rows;
    private int $daysInMonth;
    private int $month;
    private int $year;

    public function __construct(array $rows, int $daysInMonth, int $month, int $year)
    {
        $this->rows = $rows;
        $this->daysInMonth = $daysInMonth;
        $this->month = $month;
        $this->year = $year;
    }

    public function title(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function headings(): array
    {
        $headers = ['Employee', 'Code', 'Department', 'Biometric ID'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $headers[] = $d . ' ' . Carbon::create($this->year, $this->month, $d)->format('D');
        }
        return array_merge($headers, ['Present', 'Absent', 'Late', 'Paid Leave', 'Unpaid Leave', 'Off/Holiday', 'Payable Days']);
    }

    public function array(): array
    {
        $data = [];
        foreach ($this->rows as $row) {
            $line = [
                $row['employee'] ?? '',
                $row['code'] ?? '',
                $row['department'] ?? '',
                $row['biometric_id'] ?? '',
            ];
            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $line[] = $this->dayCell($row['day_' . $d] ?? null);
            }
            $line[] = $row['presents'] ?? 0;
            $line[] = $row['absents'] ?? 0;
            $line[] = $row['lates'] ?? 0;
            $line[] = $row['leaves'] ?? 0;
            $line[] = $row['npl'] ?? 0;
            $line[] = $row['offs'] ?? 0;
            $line[] = $row['payable'] ?? 0;
            $data[] = $line;
        }
        return $data;
    }

    private function dayCell(?array $day): string
    {
        if (!$day) return '';
        $status = $day['status'] ?? '';
        $hasPunch = in_array($status, ['Present', 'Present*', 'Late'], true) && ($day['in'] ?? '--') !== '--';
        return $hasPunch ? "{$status} {$day['in']}-{$day['out']}" : $status;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 22, 'B' => 12, 'C' => 16, 'D' => 12];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $widths[Coordinate::stringFromColumnIndex(4 + $d)] = 16;
        }
        return $widths;
    }

    public function registerEvents(): array
    {
        $lastCol = Coordinate::stringFromColumnIndex(4 + $this->daysInMonth + 7);
        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol) {
                $event->sheet->getDelegate()->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                $event->sheet->getDelegate()->freezePane('E2');
            },
        ];
    }
}
