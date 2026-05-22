<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesClient extends Model {
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'phone', 'email', 'address', 'city', 'gst_number', 'contact_person', 'created_by', 'tenant_id'];
    public function visits() { return $this->hasMany(SalesVisit::class, 'client_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
