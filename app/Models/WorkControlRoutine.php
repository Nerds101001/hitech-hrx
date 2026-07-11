<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkControlRoutine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'frequency',
        'duration_minutes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
