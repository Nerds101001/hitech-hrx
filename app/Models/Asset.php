<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\TenantTrait;

class Asset extends Model
{
    use TenantTrait;

    protected $fillable = [
        'tenant_id',
        'asset_code',
        'name',
        'description',
        'category_id',
        'assigned_to',
        'purchase_date',
        'purchase_cost',
        'current_value',
        'status',
        'location',
        'serial_number',
        'service_tag', // Note: This might map to 'notes' or a new column
        'brand',
        'model',
        'warranty_expiry',
        'notes',
        'extra_details',
        'warranty_bill',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
        'current_value' => 'decimal:2',
        'extra_details' => 'array',
    ];

    /**
     * Get the category that owns the asset
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    /**
     * Get the user assigned to the asset
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created the asset
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the maintenance records for the asset
     */
    /* public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class, 'asset_id');
    } */

    /**
     * Get the assignment history for the asset
     */
    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'available' => 'bg-label-success',
            'assigned' => 'bg-label-primary',
            'maintenance' => 'bg-label-warning',
            'retired' => 'bg-label-danger',
            default => 'bg-label-secondary',
        };
    }

    /**
     * Get formatted purchase cost
     */
    public function getFormattedPurchaseCostAttribute(): string
    {
        return number_format((float)($this->purchase_cost ?? 0), 2);
    }

    /**
     * Get formatted current value
     */
    public function getFormattedCurrentValueAttribute(): string
    {
        return number_format((float)($this->current_value ?? 0), 2);
    }

    /**
     * Get days until warranty expiry
     */
    public function getDaysUntilWarrantyExpiryAttribute(): int
    {
        if (!$this->warranty_expiry) {
            return -1;
        }
        
        return now()->diffInDays($this->warranty_expiry);
    }

    /**
     * Check if warranty is expiring soon (within 30 days)
     */
    public function getWarrantyExpiringSoonAttribute(): bool
    {
        if (!$this->warranty_expiry) {
            return false;
        }
        
        return now()->diffInDays($this->warranty_expiry) <= 30;
    }

    /**
     * Get warranty bill URL
     */
    public function getWarrantyBillUrlAttribute(): ?string
    {
        return $this->warranty_bill ? asset('storage/' . $this->warranty_bill) : null;
    }
}
