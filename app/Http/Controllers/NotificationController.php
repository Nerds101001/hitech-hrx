<?php
namespace App\Http\Controllers;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
class NotificationController extends Controller
{
  public function index()
  {
    $notifications = Auth::user()->notifications;
    //$users = User::all();
    //Notification::send($users, new Announcement('New Test Announcement'));
    return view('notifications.index', compact('notifications'));
  }
  public function markAsRead($id = null)
  {
    if ($id) {
      // Mark a specific notification as read
      $notification = Auth::user()->notifications()->where('id', $id)->first();
      if ($notification) {
        $notification->markAsRead();
        return redirect()->back()->with('success', 'Notification marked as read.');
      }
      return redirect()->back()->with('error', 'Notification not found.');
    } else {
      // Mark all notifications as read
      Auth::user()->unreadNotifications->markAsRead();
      return redirect()->back()->with('success', 'All notifications marked as read.');
    }
  }
  public function getNotificationsAjax()
  {
    $notifications = Auth::user()->notifications;
    return Success::response($notifications);
  }
  public function myNotifications()
  {
    $notifications = Auth::user()->notifications;
    return view('notifications.myNotifications', compact('notifications'));
  }
  
}
