<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class KingoBingoScore extends Model
{
    use TenantTrait;

    protected $fillable = [
        'salesperson_id', 'month_year',
        'razor_blade_target', 'razor_blade_achieved',
        'new_customers_target', 'new_customers_achieved',
        'upsell_target', 'upsell_achieved',
        'total_customers', 'total_orders',
        'payment_target', 'payment_achieved',
        'rate_target', 'rate_achieved',
        'nif_target', 'nif_achieved',
        'training_target', 'training_achieved',
        'mom_target', 'mom_achieved',
    ];

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    // score = (achieved / target) * multiplier, capped at 200
    public static function calcScore($achieved, $target, $multiplier = 100): float
    {
        if (!$target || $target == 0) return 0;
        return round(($achieved / $target) * $multiplier, 1);
    }
}
