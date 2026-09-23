<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CcSalespersonMap extends Model implements AuditableContract
{
    use HasFactory, Auditable;
    protected $table = 'cc_salesperson_map';
    // role: 'ccare' | 'newbiz' — which capacity this agent serves the
    // salesperson in. A combined-department agent holds one row per capacity,
    // so they can be removed from one list without affecting the other.
    protected $fillable = ['cc_user_id', 'sales_user_id', 'role', 'tenant_id'];
    public function ccUser() { return $this->belongsTo(User::class, 'cc_user_id'); }
    public function salesUser() { return $this->belongsTo(User::class, 'sales_user_id'); }
}
