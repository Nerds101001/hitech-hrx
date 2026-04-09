<?php

namespace App\Traits;

use App\Models\User;
use Constants;

trait UserActionsTrait
{
  public function createdBy()
  {
    return $this->belongsTo(User::class, 'created_by_id');
  }

  public function createdAt()
  {
    return $this->created_at->format(Constants::DateTimeFormat);
  }

  public function updatedAt()
  {
    return $this->updated_at->format(Constants::DateTimeFormat);
  }

  public function updatedBy()
  {
    return $this->belongsTo(User::class, 'updated_by_id');
  }
}
