<?php

namespace Database\Seeders;

use App\Models\SuperAdmin\SaSettings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $this->command->info('Seeding SA settings...');

    SaSettings::create([
      'app_version' => '4.2.1',
      'currency' => 'INR',
      'currency_symbol' => '₹',
      'currency_position' => 'left',
      'offline_payment_enabled' => true,
      'privacy_policy_url' => 'https://hitechhrx.com/privacy-policy/',
      'website' => 'https://hitechhrx.com',
      'support_email' => 'info@hitechgroup.in',
      'support_phone' => '+91 9814215000',
      'support_whatsapp' => '+91 9814215000',
      'offline_payment_instructions' => 'Please make your payment to the following bank account number: 1234567890',
    ]);

    $this->command->info('SA Settings seeded!');
  }
}
