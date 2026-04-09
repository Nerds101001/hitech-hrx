<?php

namespace App\Exports;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Constants;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AttendanceExport implements FromQuery, WithTitle, WithHeadings, WithMapping, WithColumnWidths, WithEvents
{
    private $month;
    private $year;

    function __construct(int $month, int $year)
    {
        $this->month = $month;
        $this->year = $year;
    }


    public function query()
    {
        return Attendance::query()
            ->whereYear('created_at', $this->year)
            ->whereMonth('created_at', $this->month);
    }

    public function title(): string
    {
        return 'Period ' . $this->month . ' ' . $this->year;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Employee ID',
            'Employee Name',
            'Shift',
            'Date',
            'Check In Time',
            'Check Out Time',
            'Total Hours',
        ];
    }

    public function map($row): array
    {

        $employee = User::where('id', $row->user_id)->first();

        $shift = Shift::where('id', $row->shift_id)->first();

        $totalHours = Carbon::parse($row->check_out_time)->diffInHours(Carbon::parse($row->check_in_time));

        return [
            $row->id,
            $employee->id,
            $employee->getFullName(),
            $shift == null ? 'N/A' : $shift->title,
            $row->created_at->format(Constants::DateFormat),
            Carbon::parse($row->check_in_time)->format(Constants::TimeFormat),
            Carbon::parse($row->check_out_time)->format(Constants::TimeFormat),
            $totalHours
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:H1')->getAlignment()->setHorizontal('center');
                $event->sheet->getDelegate()->getStyle('A1:H1')->getFont()->setBold(true);

            },
        ];
    }
}
