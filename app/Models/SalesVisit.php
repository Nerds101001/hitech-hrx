<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesVisit extends Model {
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'visit_type', 'status', 'verification_status', 'verification_notes', 'client_id', 'salesperson_id', 'cc_user_id',
        'scheduled_at', 'notes', 'proof_photo',
        'completed_lat', 'completed_lng', 'completed_at', 'completion_notes', 'tenant_id'
    ];
    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'completed_lat' => 'float',
        'completed_lng' => 'float',
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
