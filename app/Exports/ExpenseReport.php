<?php

namespace App\Exports;

use App\Models\ExpenseRequest;
use App\Models\User;
use Constants;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ExpenseReport implements FromQuery, WithTitle, WithHeadings, WithMapping, WithColumnWidths, WithEvents
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
        return ExpenseRequest::query()
            ->whereYear('created_at', $this->year)
            ->whereMonth('created_at', $this->month);
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
            'I' => 15,
            'J' => 15,
            'K' => 15,
            'L' => 15
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:K1')->getFont()->setBold(true);

                //Set auto width of all columns
                $event->sheet->getDelegate()->getColumnDimension('A')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('B')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('C')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('D')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('E')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('F')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('G')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('H')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('I')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('J')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('K')->setAutoSize(true);
                $event->sheet->getDelegate()->getColumnDimension('L')->setAutoSize(true);

            },
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Employee ID',
            'Employee Name',
            'Expense Type',
            'Amount',
            'Proof',
            'Approved Amount',
            'Status',
            'Created At',
            'Approved By',
            'Approved At',
            'Approver Remarks'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->user->id,
            $row->user->first_name . ' ' . $row->user->last_name,
            $row->expenseType->name,
            $row->amount,
            $row->document != null ? '=HYPERLINK("' . url($row->document) . '","View")' : 'No Proof',
            $row->approved_amount,
            $row->status,
            $row->created_at->format(Constants::DateTimeFormat),
            $row->approved_by_id != null ? User::find($row->approved_by_id)->getFullName() : '',
            $row->approved_at != null ? $row->approved_at->format(Constants::DateTimeFormat) : '',
            $row->approver_remarks
        ];
    }

    public function title(): string
    {
        return 'Period' . date('F Y', mktime(0, 0, 0, $this->month, 1, $this->year));
    }
}
