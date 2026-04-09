<?php

namespace App\Services\Web\NotificationService;


use Illuminate\Http\Request;

interface INotificationService
{
  public function getAllNotifications();

  public function getUserNotifications();

  public function markAllAsRead();

  public function createNotification(Request $request);

  public function markAsRead($id);

  public function deleteNotification($id);

  public function saveToken(Request $request);
}

