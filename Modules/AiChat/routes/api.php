<?php

use Illuminate\Support\Facades\Route;
use Modules\AiChat\Http\Controllers\AiChatController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('aichat/query', [AiChatController::class, 'handleQuery'])->name('aichat.query');
    Route::get('aichat/test', [AiChatController::class, 'test'])->name('aichat.test');
    Route::get('aichat/schema', [AiChatController::class, 'getSchema'])->name('aichat.schema');
});
