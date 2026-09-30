<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class LibraryFilePermission extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $fillable = [
        'file_id',
        'user_id',
    ];

    public function file()
    {
        return $this->belongsTo(LibraryFile::class, 'file_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
