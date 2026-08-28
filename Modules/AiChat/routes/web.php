<?php

use App\Http\Middleware\AddonCheckMiddleware;
use Illuminate\Support\Facades\Route;
use Modules\AiChat\Http\Controllers\AiChatController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::middleware([
    'web',
    'auth',
    AddonCheckMiddleware::class,
  ])->group(function () {
    Route::group([], function () {
      Route::get('/aiChat', [AiChatController::class, 'index'])->name('aiChat.index');
      // SECURITY (QA fix): these endpoints executed GPT-generated SQL directly
      // against the whole database (cross-tenant read/write) and dumped the users
      // table / full schema. Disabled until rebuilt on a read-only, SELECT-only,
      // tenant-scoped, admin-gated path. See QA Report 6 (ADD-C1/C2).
      // Route::post('/aiChat/query', [AiChatController::class, 'handleQuery']);
      // Route::get('/test', [AiChatController::class, 'test']);
      // Route::get('/getSchema', [AiChatController::class, 'getSchema']);
    });
});
