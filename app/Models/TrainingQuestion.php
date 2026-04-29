<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'question', 'options', 'correct_option_index', 'tenant_id', 'marks'];

    protected $casts = [
        'options' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(TrainingModule::class, 'module_id');
    }
}
