<?php
namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class MindSpeak extends Model implements AuditableContract
{
    use TenantTrait, UserActionsTrait, SoftDeletes, Auditable;

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
        'Kaizen', '360° Feedback',
    ];

    /**
     * Categories that are a proposal — the reviewer accepts or turns them down.
     * Everything else (Idea, Complaint, 360° Feedback) is feedback the reviewer
     * replies to (acknowledge/reject).
     */
    public const APPROVABLE = ['Sales Pitch', 'Seminar', 'Competitor Insights', 'Kaizen'];

    /** Categories where anonymity is mandatory — the toggle isn't offered because
     *  there is no choice; store() always sets is_anonymous = true for these. */
    public const FORCED_ANONYMOUS = ['360° Feedback'];

    /**
     * Kaizen proposals go to these two people specifically, not the general
     * admin/HR reviewer pool: Vijender Singh (GM Manufacturing) and Manoj Patle
     * (GM Quality). Admin/HR can still act as a fallback — see
     * MindSpeakController::canReview(). IDs, not emails, so this never depends
     * on a query at permission-check time.
     */
    public const KAIZEN_REVIEWER_IDS = [472, 269]; // Vijender Singh, Manoj Patle

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
