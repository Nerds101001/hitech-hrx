<?php

namespace App\Helpers;

use App\Models\Settings;
use Illuminate\Support\Facades\Log;

class TrackingHelper
{
  public function __construct()
  {
  }

  public function isUserOnline($updatedAt): bool
  {

    $settings = Settings::first();
    $offlineCheckTime = (int)($settings->offline_check_time ?? 300); // Default to 300 seconds (5 minutes)

    // Validate $updatedAt
    if (!$updatedAt || !strtotime($updatedAt)) {
      Log::error('Invalid $updatedAt value: ' . $updatedAt);
      return false;
    }

    $lastUpdated = strtotime($updatedAt);
    $currentTime = time();
    $thresholdTime = $currentTime - $offlineCheckTime;
    /*
        Log::info('Threshold Time: ' . date('Y-m-d H:i:s', $thresholdTime));
        Log::info('Last Updated Time: ' . date('Y-m-d H:i:s', $lastUpdated));*/

    // Check if the last updated timestamp is within the threshold
    return $lastUpdated > $thresholdTime;
  }


  public function getFilteredData($trackings, $distanceFilter = 0.04): array
  {
    if ($trackings->count() <= 0) {
      return [];
    }

    $finalTracking = [];

    $stillRemovedCount = 0;
    $inVehicleRemoveCount = 0;

    for ($i = 0; $i < $trackings->count(); $i++) {

      $tracking = $trackings[$i];

      //$previousTracking = $trackings[$i-1];

      if ($tracking->type == 'checked_in' || $tracking->type == 'checked_out') {

        $finalTracking[] = $tracking;
      }

      $lastRecord = $finalTracking[count($finalTracking) - 1];

      $distance = $this->GetDistance($lastRecord->latitude, $lastRecord->longitude, $tracking->latitude, $tracking->longitude);

      if ($distance < $distanceFilter) {
        $stillRemovedCount++;
      } else if ($distance < 15 && $tracking->activity == 'ActivityType.IN_VEHICLE') {
        $inVehicleRemoveCount++;
      } else {
        $finalTracking[] = $tracking;
      }

    }

    //Take 24 items from final tracking array
    return array_slice($finalTracking, 0, 24);
  }

  function GetDistance($lat1, $lon1, $lat2, $lon2): float
  {
    $R = 6371; // Radius of the earth in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
      cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
      sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $d = $R * $c; // Distance in km
    return $d;
  }

  public function getFilteredDataV2($trackings, $distanceFilter = 0.04): array
  {
    if ($trackings->count() <= 0) {
      return [];
    }

    $finalTracking = [];
    $stillRemovedCount = 0;
    $inVehicleRemoveCount = 0;

    // Start with the first tracking point
    $finalTracking[] = $trackings[0];

    for ($i = 1; $i < $trackings->count(); $i++) {
      $currentTracking = $trackings[$i];
      $previousTracking = $trackings[$i - 1]; // Compare with the previous point

      $distance = $this->GetDistance(
        $previousTracking->latitude,
        $previousTracking->longitude,
        $currentTracking->latitude,
        $currentTracking->longitude
      );

      if ($distance < $distanceFilter) {
        $stillRemovedCount++;
      } elseif ($distance < 15 && $currentTracking->activity == 'ActivityType.IN_VEHICLE') {
        $inVehicleRemoveCount++;
      } else {
        $finalTracking[] = $currentTracking;
      }
    }

    // Return a maximum of 24 items
    return $finalTracking;
  }

  /// <summary>
  /// Get filtered location points
  /// </summary>

  public function getFilteredLocationPoints($deviceLogs, $distanceFilter = 0.02): array
  {
    $filteredLocationPoints = [];
    $lastRecord = null;

    $totalTravelledDistance = 0;
    $totalSpeed = 0; // Total speed accumulator
    $speedCount = 0; // Count valid speed calculations

    for ($i = 0; $i < count($deviceLogs); $i++) {
      $deviceLog = $deviceLogs[$i];

      if ($lastRecord == null) {
        $filteredLocationPoints[] = $deviceLog;
        $lastRecord = $deviceLog;
      } else {
        $distance = $this->GetDistance(
          $lastRecord->latitude,
          $lastRecord->longitude,
          $deviceLog->latitude,
          $deviceLog->longitude
        );

        if ($distance > $distanceFilter) {
          $filteredLocationPoints[] = $deviceLog;

          // Calculate time difference in hours
          $timeDifference = strtotime($deviceLog->created_at) - strtotime($lastRecord->created_at);
          $timeDifferenceInHours = $timeDifference / 3600; // Convert to hours

          // Avoid division by zero
          if ($timeDifferenceInHours > 0) {
            $speed = $distance / $timeDifferenceInHours; // Speed = Distance / Time
            $totalSpeed += $speed;
            $speedCount++;
          }

          $totalTravelledDistance += $distance;
          $lastRecord = $deviceLog;
        }
      }
    }

    // Calculate average speed
    $averageTravelledSpeed = $speedCount > 0 ? $totalSpeed / $speedCount : 0;

    return [
      'filteredPoints' => $filteredLocationPoints,
      'totalTravelledDistance' => round($totalTravelledDistance, 2), // Round to 2 decimal places
      'averageTravelledSpeed' => round($averageTravelledSpeed, 2) // Round to 2 decimal places
    ];
  }

  public function test(): string
  {
    return 'test';
  }
}
