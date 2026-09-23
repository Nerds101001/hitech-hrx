<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class UniformRequest extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    /** What can be requested. Adding a line here is all it takes to offer more. */
    public const ITEMS = [
        'shirt'  => 'Shirt',
        'tshirt' => 'T-Shirt',
    ];

    public const SIZES = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'item_type',
        'size',
        'quantity',
        'remarks',
        'status',
        'actioned_by',
        'actioned_at',
        'decision_note',
        'tenant_id',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
        'quantity'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actionedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    /** "T-Shirt" rather than the stored "tshirt". */
    public function getItemLabelAttribute(): string
    {
        return self::ITEMS[$this->item_type] ?? ucfirst((string) $this->item_type);
    }

    /** Only a pending request can still be decided or withdrawn. */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED  => 'bg-success',
            self::STATUS_REJECTED  => 'bg-danger',
            self::STATUS_CANCELLED => 'bg-secondary',
            default                => 'bg-warning text-dark',
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
