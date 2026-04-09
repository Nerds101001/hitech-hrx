<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $fillable = [
        'title',
        'description',
        'requirement',
        'terms_and_conditions',
        'branch',
        'category',
        'skill',
        'position',
        'start_date',
        'end_date',
        'status',
        'applicant',
        'visibility',
        'code',
        'custom_question',
        'created_by',
        'salary',
        'job_type',
        'benefits',
    ];

    public static $status = [
        'active' => 'Active',
        'in_active' => 'In Active',
    ];

    public function branches()
    {
        return $this->hasOne(Site::class, 'id', 'branch');
    }

    public function categories()
    {
        return $this->hasOne(JobCategory::class, 'id', 'category');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class, 'job', 'id');
    }

    public function questions()
    {
        $ids = explode(',', $this->custom_question);

        return CustomQuestion::whereIn('id', $ids)->get();
    }

    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }
}
