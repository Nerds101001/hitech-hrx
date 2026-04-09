<?php

namespace App\Imports;

use App\Enums\Status;
use App\Models\Holiday;
use App\Models\Location;
use App\Models\Settings;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;

class HolidaysImport implements ToModel
{
  /**
   * @param Collection $collection
   */
  public function model(array $row)
  {
    $location = Location::where('code', $row[2])->first();

    return new Holiday(
      [
        'name' => $row[0],
        'date' => $row[1],
        'code' => $this->getNewHolidayCode(),
        'location_id' => $location->id,
        'status' => Status::ACTIVE,
        'created_by_id' => auth()->id(),
      ]
    );
  }

  private function getNewHolidayCode(): string
  {
    $settings = Settings::first();

    $holidayPrefix = $settings->holiday_code_prefix;

    $lastHolidayId = Holiday::latest()->first()->id;

    if ($lastHolidayId) {
      $lastHolidayId = $lastHolidayId + 1;
    } else {
      $lastHolidayId = 1;
    }

    return $holidayPrefix . str_pad($lastHolidayId, 4, '0', STR_PAD_LEFT);
  }
}
