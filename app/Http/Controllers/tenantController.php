<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class tenantController extends Controller
{
  public function index()
  {
    $tenants = Tenant::with('domains')->get();

    return view('tenant.index', compact('tenants'));
  }

  public function store(Request $request)
  {
    $request->validate([
      'name' => 'required|min:5|unique:tenants,id',
      'companyName' => 'required|min:5',
      'emailDomain' => 'required|min:5',
    ]);

    $domains = config('tenancy.central_domains');

    try {
      $tenant = Tenant::create([
        'id' => $request->name,
      ]);

      foreach ($domains as $domain) {
        $tenant->domains()->create([
          'domain' => $domain,
        ]);
      }

      tenancy()->initialize($tenant);
      Role::create(['name' => 'admin', 'guard_name' => 'web']);
      Role::create(['name' => 'hr_admin', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true, 'is_web_access_enabled' => true]);
      Role::create(['name' => 'hr_manager', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true]);
      Role::create(['name' => 'employee', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true, 'is_location_activity_tracking_enabled' => true]);
      Role::create(['name' => 'office_employee', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true]);
      Role::create(['name' => 'accounts', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true]);

      $user = User::create([
        'first_name' => 'admin',
        'last_name' => 'User',
        'email' => 'admin@' . $request->emailDomain,
        'phone' => '0987654321',
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'DEMO002',
        'email_verified_at' => now(),
        'tenant_id' => tenancy()->tenant
      ]);

      $user->assignRole('admin');

      Settings::create([
        'tenant_id' => tenancy()->tenant,
        'company_name' => $request->companyName,
      ]);

      tenancy()->end();

      return redirect()->back()->with('success', 'Tenant created successfully');

    } catch (Exception $e) {
      Log::error($e->getMessage());

      return redirect()->back()->with('error', 'Something went wrong');
    }
  }
}
