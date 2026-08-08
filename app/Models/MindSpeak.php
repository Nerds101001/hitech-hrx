<?php
namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MindSpeak extends Model {
    use TenantTrait, UserActionsTrait, SoftDeletes;

    protected $table = 'mind_speaks';

    protected $fillable = [
        'user_id',
        'category',
        'content',
        'attachment',
        'is_anonymous',
        'tenant_id',
        'status',
        'admin_remarks',
        'reviewed_by_id',
        'reviewed_at',
        'resubmitted_from_id',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

    public const STATUS_PENDING      = 'pending';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_APPROVED     = 'approved';
    public const STATUS_REJECTED     = 'rejected';

    /** The only categories the form offers, and the only ones store() accepts. */
    public const CATEGORIES = [
        'Idea', 'Complaint', 'Sales Pitch', 'Seminar', 'Competitor Insights',
    ];

    /**
     * Categories that are a proposal — the reviewer accepts or turns them down.
     * Everything else (Idea, Complaint) is feedback the reviewer replies to.
     */
    public const APPROVABLE = ['Sales Pitch', 'Seminar', 'Competitor Insights'];

    public function isApprovable(): bool
    {
        return in_array($this->category, self::APPROVABLE, true);
    }

    /** What a reviewer may do with this, given its category. */
    public function availableActions(): array
    {
        if ($this->status === self::STATUS_REJECTED) {
            return [];                      // the ball is with the employee
        }
        return $this->isApprovable()
            ? ['approve', 'reject']
            : ['acknowledge', 'reject'];
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function reviewer() {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** The rejected submission this one was sent back in place of. */
    public function original() {
        return $this->belongsTo(self::class, 'resubmitted_from_id');
    }

    public function resubmission() {
        return $this->hasOne(self::class, 'resubmitted_from_id');
    }
}
