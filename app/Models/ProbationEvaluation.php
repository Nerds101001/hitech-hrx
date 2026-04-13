<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ProbationEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'manager_id',
        'tenant_id',
        'job_knowledge',
        'quality_of_work',
        'attendance_punctuality',
        'initiative_reliability',
        'overall_performance',
        'recommendation',
        'extension_months',
        'areas_for_improvement',
        'manager_remarks',
        'hr_status',
        'hr_decision',
        'hr_remarks',
        'reviewed_by_id',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
