<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingModule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'phase_id', 'title', 'description', 'content_type', 
        'content_body', 'content_url', 'estimated_time_minutes', 
        'order', 'is_assessment_required', 'passing_percentage',
        'questions_per_test', 'show_all_at_once', 'tenant_id'
    ];

    public function phase()
    {
        return $this->belongsTo(TrainingPhase::class, 'phase_id');
    }

    public function questions()
    {
        return $this->hasMany(TrainingQuestion::class, 'module_id');
    }

    public function userProgress()
    {
        return $this->hasMany(UserTrainingProgress::class, 'module_id');
    }
}
