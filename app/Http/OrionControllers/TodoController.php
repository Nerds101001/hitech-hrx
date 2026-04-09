<?php

namespace App\Http\OrionControllers;


use App\Models\Todo;
use Orion\Concerns\DisableAuthorization;
use Orion\Http\Controllers\Controller;

class TodoController extends Controller
{

  use DisableAuthorization;

  protected $model = Todo::class;

}
