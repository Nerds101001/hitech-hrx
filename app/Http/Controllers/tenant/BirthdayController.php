<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Enums\UserAccountStatus;
use Carbon\Carbon;
use App\Notifications\BirthdayWishNotification;

use Illuminate\Support\Facades\Log;

class BirthdayController extends Controller
{
    /**
     * Check if today is the logged-in user's birthday and get their unread birthday wishes.
     * Also returns a list of other active users who have birthdays today.
     */
    public function checkBirthdays(Request $request)
    {
        $currentUser = auth()->user();
        $today = Carbon::today();
        
        // 1. Check if it's the current user's birthday
        $isMyBirthday = false;
        if ($currentUser->dob) {
            $dob = Carbon::parse($currentUser->dob);
            if ($dob->month == $today->month && $dob->day == $today->day) {
                $isMyBirthday = true;
            }
        }

        // Fetch current user's unread birthday notifications
        $unreadWishes = [];
        if ($isMyBirthday) {
            $unreadWishes = $currentUser->unreadNotifications()
                ->where('type', BirthdayWishNotification::class)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'sender_id' => $notification->data['sender_id'] ?? null,
                        'sender_name' => $notification->data['sender_name'] ?? 'Someone',
                        'sender_avatar' => $notification->data['sender_avatar'] ?? null,
                        'message' => $notification->data['message'] ?? 'Happy Birthday!',
                    ];
                });
        }

        // 2. Find colleagues who have birthdays today (excluding current user)
        $colleaguesBirthdays = User::where('status', UserAccountStatus::ACTIVE)
            ->where('id', '!=', $currentUser->id)
            ->whereNotNull('dob')
            ->whereMonth('dob', $today->month)
            ->whereDay('dob', $today->day)
            ->select('id', 'first_name', 'last_name', 'profile_picture')
            ->get();

        $alreadyWishedUserIds = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('type', BirthdayWishNotification::class)
            ->whereJsonContains('data->sender_id', $currentUser->id)
            ->whereYear('created_at', $today->year)
            ->pluck('notifiable_id')
            ->toArray();

        $colleaguesBirthdays = $colleaguesBirthdays->map(function ($user) use ($alreadyWishedUserIds) {
            $user->is_wished = in_array($user->id, $alreadyWishedUserIds);
            return $user;
        });

        return response()->json([
            'isMyBirthday' => $isMyBirthday,
            'unreadWishes' => $unreadWishes,
            'colleagues' => $colleaguesBirthdays
        ]);
    }

    /**
     * Send a birthday wish to a colleague.
     */
    public function sendWish(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $receiver = User::findOrFail($request->receiver_id);
        $sender = auth()->user();

        // Optionally, check if it is actually their birthday
        $today = Carbon::today();
        if ($receiver->dob) {
            $dob = Carbon::parse($receiver->dob);
            if ($dob->month != $today->month || $dob->day != $today->day) {
                return response()->json(['error' => 'It is not this user\'s birthday today.'], 400);
            }
        } else {
            return response()->json(['error' => 'This user has no birthday set.'], 400);
        }

        // Send the notification
        try {
            $receiver->notify(new BirthdayWishNotification($sender, $request->message));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Birthday wish notification error: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Birthday wish sent successfully!']);
    }

    /**
     * Mark the current user's unread birthday wishes as read.
     */
    public function markWishesRead(Request $request)
    {
        $currentUser = auth()->user();
        
        $currentUser->unreadNotifications()
            ->where('type', BirthdayWishNotification::class)
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
