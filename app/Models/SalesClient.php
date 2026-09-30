<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalesClient extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, Auditable;
    protected $fillable = ['crm_company_code', 'name', 'phone', 'email', 'address', 'city', 'gst_number', 'contact_person', 'created_by', 'tenant_id'];
    public function visits() { return $this->hasMany(SalesVisit::class, 'client_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
