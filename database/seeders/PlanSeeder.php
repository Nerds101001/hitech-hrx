<?php

namespace Database\Seeders;

use App\Enums\PlanDurationType;
use App\Models\SuperAdmin\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $this->command->info('Seeding plans...');

    Plan::create([
      'name' => 'Basic',
      'base_price' => 50,
      'per_user_price' => 30,
      'modules' => ["LeaveManagement", "ExpenseManagement", "ClientVisit", "ChatSystem", "BreakSystem", "DocumentManagement", "DynamicForms", "GeofenceSystem", "PaymentCollection", "ProductOrder", "QRAttendance",],
      'description' => 'This is a free plan',
      'included_users' => 1,
      'duration' => 1,
      'duration_type' => PlanDurationType::MONTHS,
    ]);

    Plan::create([
      'name' => 'Standard',
      'base_price' => 100,
      'per_user_price' => 50,
      'modules' => ["LeaveManagement", "ExpenseManagement", "ClientVisit", "ChatSystem", "BreakSystem", "DocumentManagement", "GeofenceSystem", "IpAddressAttendance", "LoanManagement", "NoticeBoard", "PaymentCollection", "ProductOrder", "QRAttendance", "SiteAttendance", "TaskSystem", "UidLogin"],
      'description' => 'This is a basic plan',
      'included_users' => 3,
      'duration' => 1,
      'duration_type' => PlanDurationType::MONTHS,
    ]);

    Plan::create([
      'name' => 'Pro',
      'base_price' => 400,
      'per_user_price' => 100,
      'modules' => ["LeaveManagement", "ExpenseManagement", "ClientVisit", "ChatSystem", "BreakSystem", "DocumentManagement", "DynamicForms", "GeofenceSystem", "IpAddressAttendance", "LoanManagement", "ManagerApp", "NoticeBoard", "OfflineTracking", "PaymentCollection", "ProductOrder", "QRAttendance", "SiteAttendance", "TaskSystem", "UidLogin", "AiChat", "DataImportExport", "DynamicQrAttendance", "Payroll", "DigitalIdCard", "SalesTarget", 'SoS'],
      'description' => 'This is a pro plan',
      'included_users' => 5,
      'duration' => 1,
      'duration_type' => PlanDurationType::YEARS,
    ]);

    $this->command->info('Plans seeded!');
  }
}
