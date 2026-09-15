<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AttendanceLogsExport implements FromQuery, WithTitle, WithHeadings, WithMapping, WithColumnWidths, WithEvents
{
    private Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function title(): string
    {
        return 'Attendance Logs';
    }

    public function headings(): array
    {
        return ['Employee', 'Employee Code', 'Department', 'Date', 'Shift', 'Check In', 'Check Out', 'Working Hours', 'Status'];
    }

    public function map($attendance): array
    {
        // Same resolver every other attendance surface uses, so this export's
        // Status column can't disagree with the on-screen badge.
        $holiday = $attendance->check_in_time
            ? \App\Services\AttendanceDayStatus::holidayForUserOnDateSingle($attendance->user, $attendance->check_in_time->toDateString())
            : null;
        $r = \App\Services\AttendanceDayStatus::resolve($attendance->user, $attendance->check_in_time ?: Carbon::today(), $attendance, null, $holiday);

        $hours = '--';
        if ($attendance->check_in_time && $attendance->check_out_time) {
            $diff = $attendance->check_in_time->diff($attendance->check_out_time);
            $hours = sprintf('%d:%02d', $diff->h, $diff->i);
        }

        return [
            $attendance->user?->getFullName() ?? 'Unknown',
            $attendance->user?->code ?? '--',
            optional($attendance->user?->department)->name ?? '--',
            $attendance->check_in_time ? $attendance->check_in_time->format('d/m/Y') : 'N/A',
            $attendance->shift?->name ?? 'N/A',
            $attendance->check_in_time ? $attendance->check_in_time->format('H:i') : '--:--',
            $attendance->check_out_time ? $attendance->check_out_time->format('H:i') : '--:--',
            $hours,
            $this->statusLabel($r['code']),
        ];
    }

    private function statusLabel(string $code): string
    {
        return match ($code) {
            'sp' => 'SP',
            'hp' => 'HP',
            'absent' => 'Absent',
            'miss-punch' => 'Miss Punch',
            'half-day' => 'Half-Day',
            'half-day-paid' => 'Present (Paid Half-Day)',
            'late' => 'Late',
            'paid_leave', 'leave' => 'Paid Leave',
            'unpaid_leave', 'on_leave' => 'Unpaid Leave',
            'work_from_home', 'wfh' => 'WFH',
            default => 'Present',
        };
    }

    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 14, 'C' => 18, 'D' => 12, 'E' => 12, 'F' => 10, 'G' => 10, 'H' => 14, 'I' => 20];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:I1')->getFont()->setBold(true);
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }
}
