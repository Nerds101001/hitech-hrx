<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
  protected $fillable = [
    'name',
    'guard_name',
    'is_location_activity_tracking_enabled',
    'is_mobile_app_access_enabled',
    'is_multiple_check_in_enabled',
    'is_web_access_enabled'
  ];

  protected $casts = [
    'is_location_activity_tracking_enabled' => 'boolean',
    'is_mobile_app_access_enabled' => 'boolean',
    'is_multiple_check_in_enabled' => 'boolean',
    'is_web_access_enabled' => 'boolean'
  ];

  /**
   * Get the role's display name in a professional format.
   * Converts 'employee' -> 'Employee', 'hr' -> 'HR', etc.
   */
  public function getDisplayNameAttribute()
  {
      $value = $this->name;
      if (!$value) return '';

      // Handle special acronyms
      $acronyms = ['HR', 'IT', 'CEO', 'CTO', 'COO'];
      
      $words = explode('_', str_replace('-', '_', $value));
      $formattedWords = array_map(function($word) use ($acronyms) {
          if (in_array(strtoupper($word), $acronyms)) {
              return strtoupper($word);
          }
          return ucwords($word);
      }, $words);

      return implode(' ', $formattedWords);
  }
}
