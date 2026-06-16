<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesVisit extends Model {
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'visit_type', 'status', 'verification_status', 'verification_notes', 'client_id', 'salesperson_id', 'cc_user_id',
        'scheduled_at', 'notes', 'started_lat', 'started_lng', 'started_at', 'proof_photo',
        'completed_lat', 'completed_lng', 'completed_at', 'completion_notes',
        'rating', 'rating_comment', 'survey_token', 'tenant_id',
        'razor_blade', 'is_new_customer', 'is_upsell', 'rate_increase',
        'is_nif', 'training_done', 'mom_submitted', 'competitor_insight', 'product_idea',
    ];
    protected $casts = [
        'scheduled_at'    => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'started_lat'     => 'float',
        'started_lng'     => 'float',
        'completed_lat'   => 'float',
        'completed_lng'   => 'float',
        'rating'          => 'integer',
        'razor_blade'     => 'boolean',
        'is_new_customer' => 'boolean',
        'is_upsell'       => 'boolean',
        'rate_increase'   => 'boolean',
        'is_nif'          => 'boolean',
        'training_done'   => 'boolean',
        'mom_submitted'   => 'boolean',
        'competitor_insight' => 'boolean',
        'product_idea'    => 'boolean',
    ];
    public function client() { return $this->belongsTo(SalesClient::class, 'client_id'); }
    public function salesperson() { return $this->belongsTo(User::class, 'salesperson_id'); }
    public function ccAgent() { return $this->belongsTo(User::class, 'cc_user_id'); }

    public static function visitTypeLabel($type) {
        return match($type) {
            'client_visit' => 'Client Visit',
            'product_trial' => 'Product Trial',
            'order_collection' => 'Order Collection',
            'service_call' => 'Service Call',
            default => ucfirst($type),
        };
    }

    public function getStatusBadgeClass() {
        return match($this->status) {
            'pending'   => 'bg-label-warning',
            'confirmed' => 'bg-label-info',
            'completed' => 'bg-label-success',
            'cancelled' => 'bg-label-danger',
            default     => 'bg-label-secondary',
        };
    }
}
