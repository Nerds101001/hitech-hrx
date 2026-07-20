<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CcSalespersonMap extends Model {
    use HasFactory;
    protected $table = 'cc_salesperson_map';
    // role: 'ccare' | 'newbiz' — which hat the agent wears for this salesperson.
    // One person can hold BOTH roles for the same salesperson (two rows). Legacy
    // rows have NULL role and fall back to the agent's department.
    protected $fillable = ['cc_user_id', 'sales_user_id', 'tenant_id', 'role'];
    public function ccUser() { return $this->belongsTo(User::class, 'cc_user_id'); }
    public function salesUser() { return $this->belongsTo(User::class, 'sales_user_id'); }
}
