<?php

namespace App\Exports;

use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use Constants;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class VisitExport implements FromQuery, WithTitle, WithHeadings, WithMapping, WithColumnWidths, WithEvents, WithDrawings
{
  private $month;
  private $year;

  function __construct(int $month, int $year)
  {
    $this->month = $month;
    $this->year = $year;
  }

  public function headings(): array
  {
    return [
      'ID',
      'Employee ID',
      'Employee Name',
      'Client',
      'Date Time',
      'Image',
      'Location',
      'Remarks',
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
        //Set Auto Size for all columns
        $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(25);

        //Set all row height to 80 except header row
        $event->sheet->getDelegate()->getDefaultRowDimension()->setRowHeight(80);
      },
    ];
  }

  public function title(): string
  {
    return 'Period ' . $this->month . ' ' . $this->year;
  }

  public function drawings()
  {
    try {
      //Filtered Visit Image from table row in Column F
      $visitImages = Visit::query()
        ->whereYear('created_at', $this->year)
        ->whereMonth('created_at', $this->month)
        ->whereNotNull('img_url')
        ->where('img_url', '!=', '')
        ->get()
        ->map(function ($visit) {
          return $visit->img_url;
        });

      //Create Drawing for each image
      $drawings = [];
      foreach ($visitImages as $key => $image) {
        // Use storage_path instead of public_path for Laravel storage files
        $imagePath = storage_path('app/public/' . Constants::BaseFolderVisitImages . $image);
        
        // Check if file exists before creating drawing
        if (file_exists($imagePath)) {
          try {
            $drawing = new Drawing();
            $drawing->setName('Visit Image');
            $drawing->setDescription('Visit Image');
            $drawing->setPath($imagePath);
            $drawing->setHeight(100);
            $drawing->setCoordinates('F' . ($key + 2));
            $drawings[] = $drawing;
          } catch (\Exception $e) {
            // Skip this image if there's an error creating the drawing
            \Log::warning('Failed to add image to visit report: ' . $e->getMessage() . ' for image: ' . $imagePath);
            continue;
          }
        }
      }

      return $drawings;
    } catch (\Exception $e) {
      // Return empty array if there's any error with drawings
      \Log::error('Failed to process visit report images: ' . $e->getMessage());
      return [];
    }
  }

  public function map($row): array
  {
    $maps = 'https://www.google.com/maps/search/?api=1&query=' . $row->latitude . ',' . $row->longitude;
    $text = '=HYPERLINK("' . $maps . '","Open in Google Maps")';

    return [
      $row->id,
      $row->createdBy ? $row->createdBy->id : 'N/A',
      $row->createdBy ? $row->createdBy->getFullName() : 'N/A',
      $row->client ? $row->client->name : 'N/A',
      $row->created_at->format(Constants::DateTimeFormat),
      $row->img_url ? '=HYPERLINK("' . asset('storage/' . Constants::BaseFolderVisitImages . $row->img_url) . '","Visit Image")' : 'No Image',
      $text,
      $row->remarks ?? 'N/A',
    ];
  }

  public function query()
  {
    return Visit::query()
      ->with(['createdBy', 'client'])
      ->whereYear('created_at', $this->year)
      ->whereMonth('created_at', $this->month);
  }
}
