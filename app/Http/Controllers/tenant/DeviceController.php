<?php

namespace App\Http\Controllers\tenant;

use Constants;
use Exception;
use App\Models\User;
use App\Enums\Status;
use App\ApiClasses\Error;
use App\Models\UserDevice;
use App\ApiClasses\Success;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class DeviceController extends Controller
{
  public function index()
  {

    $users = User::where('status', Status::ACTIVE)
      ->select('id', 'first_name', 'last_name', 'code')
      ->get();

    return view('tenant.device.index', [
      'users' => $users
    ]);
  }

  public function indexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'user',
        3 => 'deviceType',
        4 => 'brand',
        5 => 'model',
        6 => 'appVersion',
      ];

      $query = UserDevice::query();

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      $totalData = $query->count();

      if ($order == 'id') {
        $order = 'user_devices.id';
        $query->orderBy($order, $dir);
      }

      if ($request->has('userFilter') && !empty($request->input('userFilter'))) {
        $query->where('user_devices.user_id', $request->input('userFilter'));
      }

      if (empty($request->input('search.value'))) {
        $userDevices = $query->select('user_devices.*', 'user.first_name', 'user.last_name', 'user.code', 'user.profile_picture')
          ->leftJoin('users as user', 'user_devices.user_id', '=', 'user.id')
          ->offset($start)
          ->limit($limit)
          ->get();
      } else {
        $search = $request->input('search.value');
        $userDevices = $query->select('user_devices.*', 'user.first_name', 'user.last_name', 'user.code', 'user.profile_picture')
          ->leftJoin('users as user', 'user_devices.user_id', '=', 'user.id')
          ->where(function ($query) use ($search) {
            $query->where('user_devices.id', 'LIKE', "%{$search}%")
              ->orWhere('user_devices.user_id', 'LIKE', "%{$search}%")
              ->orWhere('user.first_name', 'LIKE', "%{$search}%")
              ->orWhere('user.last_name', 'LIKE', "%{$search}%")
              ->orWhere('user.code', 'LIKE', "%{$search}%");
          })
          ->offset($start)
          ->limit($limit)
          ->get();
      }

      $totalFiltered = $query->count();

      $data = [];

      if (!empty($userDevices)) {
        foreach ($userDevices as $userDevice) {
          $nestedData['id'] = $userDevice->id;
          $nestedData['user_id'] = $userDevice->user_id;
          $nestedData['device_type'] = $userDevice->device_type;
          $nestedData['brand'] = $userDevice->brand;
          $nestedData['model'] = $userDevice->model;
          $nestedData['app_version'] = $userDevice->app_version;

          //user
          $nestedData['user_name'] = $userDevice->user->getFullName();
          $nestedData['user_initial'] = $userDevice->user->getInitials();
          $nestedData['user_code'] = $userDevice->user->code;
          $nestedData['user_profile_image'] =
            $userDevice->user->profile_picture != null ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $userDevice->user->profile_picture) : null;

          $data[] = $nestedData;
        }
      }
      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'data' => $data,
      ]);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong');
    }
  }

  public function getByIdAjax($id)
  {
    $userDevice = UserDevice::findOrFail($id);

    if (!$userDevice) {
      return Error::response('Device status log not found');
    }
    $response = [
      'id' => $userDevice->id,
      'userName' => $userDevice->user->getFullName(),
      'userCode' => $userDevice->user->code,
      'deviceType' => $userDevice->device_type,
      'brand' => $userDevice->brand,
      'model' => $userDevice->model,
      'appVersion' => $userDevice->app_version,
      'sdkVersion' => $userDevice->sdk_version,
      'createdAt' => $userDevice->created_at->format(Constants::DateTimeFormat),
      'latitude' => $userDevice->latitude,
      'longitude' => $userDevice->longitude,
    ];

    return Success::response($response);
  }
  public function deleteAjax($id)
  {
    $userDevice = UserDevice::findOrFail($id);
    if (!$userDevice) {
      return Error::response('Device status log not found');
    }
    $userDevice->delete();
    return Success::response('Device status log deleted successfully');
  }
}
