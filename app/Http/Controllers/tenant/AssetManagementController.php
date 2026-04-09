<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssetManagementController extends Controller
{
    public function index()
    {
        return view('tenant.assets.index');
    }
}
