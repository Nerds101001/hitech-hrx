<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnHitechLog extends Model
{
    protected $fillable = [
        'library_file_id',
        'user_id',
        'awarded_by_id',
        'awarded_points',
        'tenant_id'
    ];

    public function libraryFile()
    {
        return $this->belongsTo(LibraryFile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by_id');
    }
}
