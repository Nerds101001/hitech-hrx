<?php

namespace App\Http\Controllers;

class DemoController extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('demo.index', ['pageConfigs' => $pageConfigs]);
  }
}
