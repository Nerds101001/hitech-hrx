<?php

namespace Database\Seeders;

use App\Enums\DomainRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\SuperAdmin\DomainRequest;
use App\Models\SuperAdmin\Order;
use App\Models\SuperAdmin\Plan;
use App\Models\SuperAdmin\Subscription;
use App\Models\User;
use App\Services\PlanService\ISubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    Artisan::call('cache:clear');

    $this->call(DemoSeeder::class);
  }

}
