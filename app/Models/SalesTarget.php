<?php

namespace App\Models;

use App\Enums\IncentiveType;
use App\Enums\TargetStatus;
use App\Enums\TargetType;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalesTarget extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'sales_targets';

  protected $fillable = [
    'user_id',
    'target_type',
    'period',
    'expiry_date',
    'target_amount',
    'achieved_amount',
    'remaining_amount',
    'incentive_amount',
    'incentive_percentage',
    'last_evaluated_date',
    'status',
    'incentive_type',
    'description',
    'notes',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'period' => 'integer',
    'expiry_date' => 'date',
    'target_amount' => 'float',
    'achieved_amount' => 'float',
    'remaining_amount' => 'float',
    'incentive_amount' => 'float',
    'incentive_percentage' => 'float',
    'last_evaluated_date' => 'date',
    'target_type' => TargetType::Class,
    'status' => TargetStatus::Class,
    'incentive_type' => IncentiveType::Class,
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function orders()
  {
    return $this->hasMany(ProductOrder::class, 'user_id', 'user_id')
      ->where('status', 'completed')
      ->whereYear('created_at', $this->period);
  }

  public function getAchievedAmount()
  {
    $logs = $this->logs()->get();
    return $logs->sum('achieved_amount') ?? 0;
  }

  public function logs()
  {
    return $this->hasMany(SalesTargetLog::class);
  }

  /**
   * Update achievement dynamically based on target type.
   */
  public function updateAchievement()
  {
    $currentDate = Carbon::now();

    switch ($this->target_type) {
      case TargetType::DAILY:
        $this->evaluateTarget('today', 'Y-m-d');
        break;

      case TargetType::WEEKLY:
        $this->evaluateTarget('this week', 'Y-\WW');
        break;

      case TargetType::MONTHLY:
        $this->evaluateTarget('this month', 'Y-m');
        break;

      case TargetType::QUARTERLY:
        $quarter = ceil($currentDate->month / 3);
        $this->evaluateTarget('this quarter', "Y-Q$quarter");
        break;

      case TargetType::HALF_YEARLY:
        $half = $currentDate->month <= 6 ? 'H1' : 'H2';
        $this->evaluateTarget('this half-year', "Y-$half");
        break;

      case TargetType::YEARLY:
        $this->evaluateTarget('this year', 'Y');
        break;
    }
  }

  /**
   * Evaluate target based on given type.
   *
   * @param string $periodDescription
   * @param string $dateFormat
   */
  protected function evaluateTarget($periodDescription, $dateFormat): void
  {
    $today = now();
    $periodIdentifier = $today->format($dateFormat);

    $log = $this->logs()->firstOrCreate(
      ['date' => $periodIdentifier],
      [
        'achieved_amount' => 0,
        'remaining_amount' => $this->target_amount,
        'status' => TargetStatus::PENDING,
      ]
    );

    $orders = ProductOrder::where('user_id', $this->user_id)
      ->where('status', 'completed');

    switch ($this->target_type) {
      case TargetType::DAILY:
        $orders->whereDate('created_at', $today->toDateString());
        break;

      case TargetType::WEEKLY:
        $orders->whereBetween('created_at', [
          $today->startOfWeek()->toDateString(),
          $today->endOfWeek()->toDateString(),
        ]);
        break;

      case TargetType::MONTHLY:
        $orders->whereMonth('created_at', $today->month)
          ->whereYear('created_at', $today->year);
        break;

      case TargetType::QUARTERLY:
        $orders->whereBetween('created_at', [
          $today->startOfQuarter()->toDateString(),
          $today->endOfQuarter()->toDateString(),
        ]);
        break;

      case TargetType::HALF_YEARLY:
        $start = $today->month <= 6 ? $today->startOfYear() : $today->startOfYear()->addMonths(6);
        $end = $today->month <= 6 ? $today->startOfYear()->addMonths(6)->endOfMonth() : $today->endOfYear();
        $orders->whereBetween('created_at', [$start, $end]);
        break;

      case TargetType::YEARLY:
        $orders->whereYear('created_at', $today->year);
        break;
    }

    $achievedAmount = $orders->sum('total');

    // Update Log
    $log->achieved_amount = $achievedAmount;
    $log->remaining_amount = max($this->target_amount - $achievedAmount, 0);
    $log->status = $achievedAmount >= $this->target_amount ? TargetStatus::COMPLETED : TargetStatus::PENDING;
    $log->save();

    // Update Main Target
    $this->achieved_amount = $achievedAmount;
    $this->remaining_amount = max($this->target_amount - $achievedAmount, 0);
    $this->status = $achievedAmount >= $this->target_amount ? TargetStatus::COMPLETED : TargetStatus::PENDING;
    $this->last_evaluated_date = now();
    $this->save();
  }
}
