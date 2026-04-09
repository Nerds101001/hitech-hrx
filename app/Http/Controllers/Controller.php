<?php

namespace App\Http\Controllers;

abstract class Controller
{
  function IsNullOrEmptyString(string|null $str)
  {
    return $str === null || trim($str) === '';
  }
}
