<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TrainingQuestion extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $fillable = ['module_id', 'question', 'options', 'correct_option_index', 'category', 'tenant_id', 'marks'];

    protected $casts = [
        'options' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(TrainingModule::class, 'module_id');
    }
}
