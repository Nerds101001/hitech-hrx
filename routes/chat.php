<?php

use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware([
  'api',
])->group(function () {

  Route::middleware('auth:api')->group(function () {
    Route::group(['middleware' => 'api', 'as' => 'api.',], function () {
      Route::group(['prefix' => 'V1/'], function () {
        Route::prefix('chats')->group(function () {
          Route::get('/', [ChatController::class, 'getChats']);
          Route::post('create', [ChatController::class, 'createChat']);
          Route::get('oneToOne/{userId}', [ChatController::class, 'getOneToOneChat']);
          Route::get('messages', [ChatController::class, 'getChatMessages']);
          Route::get('getNewChatMessages', [ChatController::class, 'getNewChatMessages']);
          Route::post('{chatId}/send', [ChatController::class, 'sendMessage']);
          Route::post('{chatId}/sendFile', [ChatController::class, 'sendFile']);
          Route::post('{chatId}/forwardFile', [ChatController::class, 'forwardFile']);
          Route::post('{chatId}/add-participant', [ChatController::class, 'addParticipant']);
          Route::get('{chatId}/participants', [ChatController::class, 'getParticipants']);
          Route::post('message/{messageId}/read', [ChatController::class, 'markAsRead']);
          Route::post('message/{messageId}/react', [ChatController::class, 'addReaction']);
          Route::post('{chatId}/upload-file', [ChatController::class, 'uploadFile']);
        });
      });
    });
  });

  Route::middleware(['auth:api'])->prefix('V1/calls')->group(function () {
    Route::post('initiate', [CallController::class, 'testToken']);
    Route::post('{channelId}/complete', [CallController::class, 'completeCall']);
    Route::get('{channelId}/status', [CallController::class, 'getCallStatus']);
    Route::get('testToken', [CallController::class, 'testToken']);
  });
});
