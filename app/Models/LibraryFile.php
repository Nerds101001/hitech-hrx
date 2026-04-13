<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryFile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'product_name',
        'category',
        'youtube_url',
        'file_path',
        'mime_type',
        'size',
        'description',
        'summary',
        'key_properties',
        'hazards',
        'usage_instructions',
        'tags',
        'is_public',
        'created_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'key_properties' => 'array',
        'hazards' => 'array',
        'usage_instructions' => 'array',
        'tags' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function permissions()
    {
        return $this->hasMany(LibraryFilePermission::class, 'file_id');
    }

    public function permittedUsers()
    {
        return $this->belongsToMany(User::class, 'library_file_permissions', 'file_id', 'user_id');
    }
}
