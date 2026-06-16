<?php

namespace App\Http\Controllers;

abstract class Controller extends \Illuminate\Routing\Controller
{
  function IsNullOrEmptyString(string|null $str)
  {
    return $str === null || trim($str) === '';
  }
}
