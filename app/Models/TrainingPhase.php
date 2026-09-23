<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TrainingPhase extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = ['title', 'description', 'order', 'tenant_id'];

    public function modules()
    {
        return $this->hasMany(TrainingModule::class, 'phase_id')->orderBy('order');
    }
}
