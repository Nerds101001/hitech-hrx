<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HRPolicy extends Model
{
    use SoftDeletes;

    protected $table = 'hr_policies';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'file_path',
        'category',
        'is_mandatory',
        'show_as_popup',
        'is_active',
        'created_by_id'
    ];

    public function acknowledgments()
    {
        return $this->hasMany(HRPolicyAcknowledgment::class, 'hr_policy_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
