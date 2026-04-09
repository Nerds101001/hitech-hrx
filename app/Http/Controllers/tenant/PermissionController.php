<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
  public function index()
  {
    return view('tenant.permission.index');
  }
}
