<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\DomainRequestStatus;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\Settings;
use App\Models\Shift;
use App\Models\SuperAdmin\DomainRequest;
use App\Models\SuperAdmin\Plan;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class DomainRequestController extends Controller
{
  public function index()
  {
    return view('superAdmin.domainRequest.index');
  }

  public function indexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'user',
        3 => 'name',
        4 => 'created_at',
        5 => 'status',
      ];


      $query = DomainRequest::query();

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      $totalData = $query->count();

      if ($order == 'id') {
        $order = 'domain_requests.id';
        $query->orderBy($order, $dir);
      }

      if (empty($request->input('search.value'))) {
        $domainRequests = $query->select(
          'domain_requests.*',
          'user.first_name',
          'user.last_name',
          'user.email',
        )
          ->leftJoin('users as user', 'domain_requests.user_id', '=', 'user.id')
          ->offset($start)
          ->limit($limit)
          ->get();
      } else {
        $search = $request->input('search.value');
        $domainRequests = $query->select(
          'domain_requests.*',
          'user.first_name',
          'user.last_name',
          'user.email',
        )
          ->leftJoin('users as user', 'domain_requests.user_id', '=', 'user.id')
          ->where(function ($query) use ($search) {
            $query->where('domain_requests.id', 'like', '%' . $search . '%')
              ->orWhere('domain_requests.user_id', 'like', '%' . $search . '%')
              ->orWhere('user.first_name', 'like', '%' . $search . '%')
              ->orWhere('user.last_name', 'like', '%' . $search . '%')
              ->orWhere('user.email', 'like', '%' . $search . '%');
          })
          ->offset($start)
          ->limit($limit)
          ->get();
      }

      $totalFiltered = $domainRequests->count();

      $data = [];

      if (!empty($domainRequests)) {
        foreach ($domainRequests as $domainRequest) {
          $nestedData['id'] = $domainRequest->id;
          $nestedData['user'] = $domainRequest->user_id;
          $nestedData['name'] = $domainRequest->name;
          $nestedData['created_at'] = $domainRequest->created_at->format(Constants::DateTimeFormat);
          $nestedData['status'] = $domainRequest->status;

          //user
          $nestedData['user_name'] = $domainRequest->user->getFullName();
          $nestedData['user_email'] = $domainRequest->user->email;
          $nestedData['user_initials'] = $domainRequest->user->getInitials();
          $nestedData['user_profile_image'] = $domainRequest->user->profile_picture != null ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $domainRequest->user->profile_picture) : null;

          $data[] = $nestedData;
        }
      }
      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => $totalData,
        'recordsFiltered' => $totalFiltered,
        'data' => $data,
      ]);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong');
    }
  }

  public function getByIdAjax($id)
  {
    $domainRequest = DomainRequest::findOrFail($id);
    if (!$domainRequest) {
      return Error::response('Domain request not found');
    }
    $response = [
      'id' => $domainRequest->id,
      'userName' => $domainRequest->user->getFullName(),
      'userEmail' => $domainRequest->user->email,
      'name' => $domainRequest->name,
      'status' => $domainRequest->status,
      'createdAt' => $domainRequest->created_at->format(Constants::DateTimeFormat),
    ];
    return Success::response($response);
  }

  public function actionAjax(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $validated = $request->validate([
      'id' => 'required|exists:domain_requests,id',
      'status' => 'required|in:approved,rejected',
      'adminNotes' => 'nullable|string',
    ]);

    try {

      $domainRequest = DomainRequest::findOrFail($validated['id']);
      $domainRequest->status = DomainRequestStatus::from($validated['status']);

      if ($validated['status'] == 'approved') {
        $domainRequest->approve_reason = $validated['adminNotes'] ?? null;
      } else {
        $domainRequest->reject_reason = $validated['adminNotes'] ?? null;
      }

      $domainRequest->save();

      if ($domainRequest->status == DomainRequestStatus::APPROVED) {
        $this->createTenant($domainRequest);
      }

      return back()->with('success', 'Domain request ' . $validated['status'] . ' successfully.');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return back()->with('error', 'Something went wrong. Please try again.');
    }
  }

  private function createTenant(DomainRequest $domainRequest): void
  {
    try {
      $domains = config('tenancy.central_domains');

      $user = User::findOrFail($domainRequest->user_id);

      $plan = Plan::findOrFail($user->plan_id);

      $tenant = Tenant::create([
        'id' => $user->email,
      ]);

      $user->tenant_id = $tenant->id;
      $user->save();

      foreach ($domains as $domain) {
        $tenant->domains()->create([
          'domain' => $domainRequest->name . '.' . $domain,
        ]);
      }

      tenancy()->initialize($tenant);

      Role::create(['name' => 'admin', 'guard_name' => 'web']);
      Role::create(['name' => 'hr', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true]);
      Role::create(['name' => 'user', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true]);
      Role::create(['name' => 'manager', 'guard_name' => 'web', 'is_mobile_app_access_enabled' => true]);

      $team = new Team();
      $team->name = 'Default Team';
      $team->code = 'TM-001';
      $team->status = 'active';
      $team->is_chat_enabled = true;
      $team->tenant_id = $tenant->id;

      $team->save();

      $shift = new Shift();
      $shift->name = 'Default Shift';
      $shift->code = 'SH-001';
      $shift->status = 'active';
      $shift->start_date = now();
      $shift->start_time = '09:00:00';
      $shift->end_time = '18:00:00';
      $shift->is_default = true;
      $shift->sunday = false;
      $shift->monday = true;
      $shift->tuesday = true;
      $shift->wednesday = true;
      $shift->thursday = true;
      $shift->friday = true;
      $shift->saturday = false;
      $shift->tenant_id = $tenant->id;

      $shift->save();

      $department = Department::create([
        'name' => 'Default Department',
        'code' => 'DEP-001',
        'status' => 'active',
        'tenant_id' => $tenant->id,
      ]);

      $designation = Designation::create([
        'name' => 'Default Designation',
        'code' => 'DES-001',
        'status' => 'active',
        'department_id' => $department->id,
        'tenant_id' => $tenant->id,
      ]);

      $newUser = User::create([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'phone' => $user->phone,
        'phone_verified_at' => now(),
        'password' => bcrypt('123456'),
        'code' => 'USR-O001',
        'email_verified_at' => now(),
        'team_id' => $team->id,
        'shift_id' => $shift->id,
        'designation_id' => $designation->id,
        'is_sa_user' => true,
        'tenant_id' => $tenant->id,
      ]);

      //TODO: Seed Company details too
      Settings::create([
        'available_modules' => $plan->modules,
        'tenant_id' => $tenant->id,
      ]);

      $newUser->assignRole('admin');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      Error::response('Something went wrong');
      return;
    }
  }
}
