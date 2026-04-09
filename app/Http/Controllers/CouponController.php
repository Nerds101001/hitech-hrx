<?php

namespace App\Http\Controllers;

use Constants;
use App\Enums\Status;
use App\ApiClasses\Error;
use App\ApiClasses\Success;
use Illuminate\Http\Request;
use App\Models\SuperAdmin\Coupon;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
  public function index()
  {
    return view('superAdmin.coupon.index');
  }


  public function indexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'discount_type',
        3 => 'code',
        4 => 'expiry_date',
        5 => 'status',
      ];

      $search = [];

      $totalData = Coupon::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      if (empty($request->input('search.value'))) {
        $coupons = Coupon::offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');
        $coupons = Coupon::where('id', 'like', "%{$search}%")
          ->orWhere('discount_type', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('expiry_date', 'like', "%{$search}%")
          ->get();

        $totalFiltered = Coupon::where('id', 'like', "%{$search}%")
          ->orWhere('discount_type', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('expiry_date', 'like', "%{$search}%")
          ->count();
      }

      $data = [];

      if (!empty($coupons)) {
        foreach ($coupons as $coupon) {
          $nestedData['id'] = $coupon->id;
          $nestedData['discount_type'] = $coupon->discount_type;
          $nestedData['code'] = $coupon->code;
          $nestedData['expiry_date'] = $coupon->expiry_date->format(Constants::DateFormat);
          $nestedData['status'] = $coupon->status;
          $data[] = $nestedData;
        }

        Log::info($coupon);
      }

      return response()->json([
        "draw" => intval($request->input('draw')),
        "recordsTotal" => $totalData,
        "recordsFiltered" => $totalFiltered,
        "code" => 200,
        "data" => $data
      ]);
    } catch (\Exception $e) {
      return Error::response($e->getMessage());
    }
  }


  private function generateUniqueCode()
  {
    $character = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    for ($i = 0; $i < 10; $i++) {
      $randomString = substr(str_shuffle($character), 0, 4);
      $numericPart = rand(1000, 9999);
      $code = $randomString . '-' . $numericPart;

      if (!Coupon::where('code', $code)->exists()) {
        return $code;
      }
    }
  }

  public function generateUniqueCodeAjax()
  {
    return response()->json($this->generateUniqueCode());
  }


  public function createAjax(Request $request)
  {

    $request->validate([
      'discountType' => 'required',
      'code' => 'required',
      'expiryDate' => 'required',
      'discount' => 'required',
      'limit' => 'required',
      'description' => 'nullable',
    ]);
    try {
      $coupon = new Coupon;
      $coupon->discount_type = $request->discountType;
      $coupon->code = $request->code;
      $coupon->expiry_date = $request->expiryDate;
      $coupon->discount = $request->discount;
      $coupon->limit = $request->limit;
      $coupon->description = $request->description;

      $coupon->save();
      return Success::response('Created');
    } catch (\Exception $e) {
      return Error::response($e->getMessage());
    }
  }

  public function changeStatusAjax($id)
  {
    $coupon = Coupon::findOrFail($id);

    if (!$coupon) {
      return Error::response('Coupon not found');
    }
    $coupon->status = $coupon->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $coupon->save();
    return Success::response('Status changed successfully');
  }

  public function deleteAjax($id)
  {
    $coupon = Coupon::findOrFail($id);
    if (!$coupon) {
      return Error::response('Coupon not found');
    }
    $coupon->delete();
    return Success::response('Coupon deleted successfully');
  }
}
