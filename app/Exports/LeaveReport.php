<?php

namespace App\Exports;

use App\Models\LeaveRequest;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class LeaveReport implements FromQuery, WithTitle, WithHeadings, WithMapping, WithColumnWidths, WithEvents
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
    return LeaveRequest::query()
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
      'J' => 15
    ];
  }


  public function registerEvents(): array
  {
    return [
      AfterSheet::class => function (AfterSheet $event) {
        $event->sheet->getDelegate()->getStyle('A1:J1')->getFont()->setBold(true);
      },
    ];
  }

  public function headings(): array
  {
    return [
      'ID',
      'Employee ID',
      'Employee Name',
      'Leave Type',
      'From',
      'To',
      'Status',
      'Proof',
      'Remarks',
      'Approver Remarks'
    ];
  }

  public function map($row): array
  {
    return [
      $row->id,
      $row->user_id,
      $row->user->first_name . ' ' . $row->user->last_name,
      $row->leaveType->name,
      $row->from_date,
      $row->to_date,
      $row->status->value,
      $row->document != null ? '=HYPERLINK("' . url($row->document) . '","View")' : 'No Proof',
      $row->remarks,
      $row->approver_remarks
    ];
  }

  public function title(): string
  {
    return 'Period ' . $this->month . ' ' . $this->year;
  }

}
