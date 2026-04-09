<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
  public function getAll(Request $request)
  {
    $validated = $request->validate([
      'skip' => 'nullable|integer',
      'take' => 'nullable|integer',
      'isRead' => 'nullable|boolean',
    ]);

    $skip = $validated['skip'] ?? 0;
    $take = $validated['take'] ?? 10;

    $query = Notification::where('notifiable_id', auth()->id())
      ->where('notifiable_type', 'App\Models\User');

    if (isset($validated['isRead'])) {
      $query->where('read_at', $validated['isRead'] ? '!=' : '=', null);
    }

    $query->orderBy('created_at', 'desc');

    $totalCount = $query->count();

    $notifications = $query->skip($skip)->take($take)->get();

    $notifications->map(function ($notification) {
      $notification->typeRaw = $notification->type;
      $notification->type = $notification->getTypeString();
      $notification->createdAtHuman = $notification->created_at->diffForHumans();
      return $notification;
    });

    $response = [
      'items' => $notifications,
      'totalCount' => $totalCount,
    ];

    return Success::response($response);
  }

  public function markAsRead($id)
  {
    $notification = Notification::find($id);

    if (!$notification) {
      return Success::response('Notification not found');
    }

    $notification->read_at = now();
    $notification->save();

    return Success::response('Notification marked as read');
  }

}
