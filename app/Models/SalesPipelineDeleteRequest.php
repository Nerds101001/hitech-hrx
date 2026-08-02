<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A request to delete a customer from the Sales Report / Sales Pipeline.
 *
 * The sales floor can raise one with a reason; only admin/HR may approve, and
 * approval is what actually deletes the customer. Fully audited so the trail
 * survives even after the underlying customer row is gone.
 */
class SalesPipelineDeleteRequest extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $table = 'sales_pipeline_delete_requests';

    protected $fillable = [
        'sales_pipeline_id', 'party_name', 'book',
        'requested_by', 'reason', 'remarks',
        'status', 'actioned_by', 'actioned_at', 'decision_note',
        'tenant_id',
    ];

    protected $casts = ['actioned_at' => 'datetime'];

    // The DB default only lands on the stored row, not on the instance you just
    // created — so code reading ->status straight after create() saw an empty
    // string. Declaring it here keeps the object and the row in agreement.
    protected $attributes = ['status' => 'pending'];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** The dropdown the requester picks from. */
    public const REASONS = [
        'duplicate'  => 'Duplicate',
        'legal_case' => 'Legal case',
        'closed'     => 'Closed',
    ];

    public const BOOK_REPORT   = 'report';
    public const BOOK_PIPELINE = 'pipeline';

    public function pipeline()
    {
        return $this->belongsTo(SalesPipeline::class, 'sales_pipeline_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actioner()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function scopePending($q)  { return $q->where('status', self::STATUS_PENDING); }
    public function scopeApproved($q) { return $q->where('status', self::STATUS_APPROVED); }
    public function scopeRejected($q) { return $q->where('status', self::STATUS_REJECTED); }

    /** Human label for the reason code, falling back to the raw value. */
    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', (string) $this->reason));
    }

    public function getBookLabelAttribute(): string
    {
        return $this->book === self::BOOK_PIPELINE ? 'Sales Pipeline' : 'Sales Report';
    }

    /** Only admin/HR decide these — deletion is irreversible. */
    public static function canApprove($user): bool
    {
        return $user && $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR']);
    }
}
