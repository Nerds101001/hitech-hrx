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
        'tenant_id'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
