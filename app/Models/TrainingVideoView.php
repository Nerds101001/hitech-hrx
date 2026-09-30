<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One person's viewing of one Digital Library video inside training. */
class TrainingVideoView extends Model
{
    protected $fillable = ['user_id', 'library_file_id', 'started_at', 'completed_at', 'tenant_id'];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function libraryFile()
    {
        return $this->belongsTo(LibraryFile::class, 'library_file_id');
    }
}
