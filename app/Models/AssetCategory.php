<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\TenantTrait;

class AssetCategory extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'parameters',
        'created_by',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];

    /**
     * Get the assets for this category
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    /**
     * Get the user who created the category
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get active assets count
     */
    public function getActiveAssetsCountAttribute(): int
    {
        return $this->assets()->where('status', 'available')->count();
    }

    /**
     * Get total assets count
     */
    public function getTotalAssetsCountAttribute(): int
    {
        return $this->assets()->count();
    }
}
