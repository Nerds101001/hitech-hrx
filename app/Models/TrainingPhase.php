<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPhase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['title', 'description', 'order', 'tenant_id'];

    public function modules()
    {
        return $this->hasMany(TrainingModule::class, 'phase_id')->orderBy('order');
    }
}
