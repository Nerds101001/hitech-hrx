<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppraisalScorecard extends Model
{
    use TenantTrait, SoftDeletes;

    protected $fillable = [
        'user_id',
        'appraisal_cycle_id',
        
        'revenue_achieved',
        'revenue_target',
        'pipeline_value',
        'yoy_growth_percentage',
        'demos_conducted',
        'new_customer_revenue_percentage',
        'ninety_day_goal_achievement',
        'sales_pitch_score_raw',
        'learn_hitech_programs',
        'seminars_conducted',
        'competitor_insights_count',
        'product_ideas_count',
        'cost_of_selling_percentage',
        'cross_sell_dominant_percentage',
        'market_share_percentage',
        'attrition_rate',
        
        'revenue_score',
        'pipeline_score',
        'yoy_growth_score',
        'demos_score',
        'new_customer_score',
        'ninety_day_goal_score',
        'sales_pitch_score',
        'learn_hitech_score',
        'seminars_score',
        'competitor_insights_score',
        'product_ideas_score',
        'cost_of_selling_score',
        'cross_sell_score',
        'market_share_score',
        'attrition_penalty',
        
        'total_score',
        'grade',
        
        'manager_comments',
        'review_frequency',
        'role_recommendation',
        'training_requirement',
        'status',
        
        'tenant_id',
    ];

    protected $casts = [
        'revenue_achieved' => 'float',
        'revenue_target' => 'float',
        'pipeline_value' => 'float',
        'yoy_growth_percentage' => 'float',
        'new_customer_revenue_percentage' => 'float',
        'ninety_day_goal_achievement' => 'float',
        'cost_of_selling_percentage' => 'float',
        'cross_sell_dominant_percentage' => 'float',
        'market_share_percentage' => 'float',
        'attrition_rate' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }
}
