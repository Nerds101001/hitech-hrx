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

        \Illuminate\Support\Facades\Log::info('Anniversary debug: Birthday check start', [
            'today' => $today->toDateString(),
            'month' => $today->month,
            'day' => $today->day,
            'current_user_id' => $currentUser->id,
        ]);
        
        $allUsers = User::select('id', 'first_name', 'last_name', 'status', 'date_of_joining')->get();
        foreach ($allUsers as $u) {
            if ($u->date_of_joining) {
                $doj = Carbon::parse($u->date_of_joining);
                \Illuminate\Support\Facades\Log::info("Anniversary debug: DOJ check for user {$u->first_name} {$u->last_name}", [
                    'id' => $u->id,
                    'status' => $u->status,
                    'doj' => $doj->toDateString(),
                    'doj_month' => $doj->month,
                    'doj_day' => $doj->day,
                    'matches' => ($doj->month == $today->month && $doj->day == $today->day)
                ]);
            }
        }
        
        // 1. Check if it's the current user's birthday
        $isMyBirthday = false;
        if ($currentUser->dob) {
            $dob = Carbon::parse($currentUser->dob);
            if ($dob->month == $today->month && $dob->day == $today->day) {
                $isMyBirthday = true;
            }
        }

        // 2. Check if today is the current user's work anniversary
        $isMyAnniversary = false;
        if ($currentUser->date_of_joining) {
            $doj = Carbon::parse($currentUser->date_of_joining);
            if ($doj->month == $today->month && $doj->day == $today->day) {
                $isMyAnniversary = true;
            }
        }

        // Fetch current user's unread work anniversary wishes
        $unreadAnniversaryWishes = [];
        if ($isMyAnniversary) {
            $unreadAnniversaryWishes = $currentUser->unreadNotifications()
                ->where('type', \App\Notifications\WorkAnniversaryWishNotification::class)
                ->get()
                ->map(function ($notification) {
                    $sender = User::find($notification->data['sender_id'] ?? null);
                    return [
                        'id' => $notification->id,
                        'sender_id' => $notification->data['sender_id'] ?? null,
                        'sender_name' => $notification->data['sender_name'] ?? 'Someone',
                        'sender_avatar' => $sender ? $sender->getProfilePicture() : null,
                        'message' => $notification->data['message_raw'] ?? 'Happy Anniversary!',
                    ];
                });
        }

        // 3. Find colleagues who have birthdays today (excluding current user)
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
            $user->avatar_url = $user->getProfilePicture(); // Resolved storage/signed URL
            return $user;
        });

        // 4. Find colleagues who have work anniversaries today (excluding current user)
        $colleaguesAnniversaries = User::where('status', UserAccountStatus::ACTIVE)
            ->where('id', '!=', $currentUser->id)
            ->whereNotNull('date_of_joining')
            ->whereMonth('date_of_joining', $today->month)
            ->whereDay('date_of_joining', $today->day)
            ->select('id', 'first_name', 'last_name', 'profile_picture', 'date_of_joining')
            ->get();

        $alreadyWishedAnniversaryIds = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('type', \App\Notifications\WorkAnniversaryWishNotification::class)
            ->whereJsonContains('data->sender_id', $currentUser->id)
            ->whereYear('created_at', $today->year)
            ->pluck('notifiable_id')
            ->toArray();

        $colleaguesAnniversaries = $colleaguesAnniversaries->map(function ($user) use ($alreadyWishedAnniversaryIds) {
            $user->is_wished = in_array($user->id, $alreadyWishedAnniversaryIds);
            $user->avatar_url = $user->getProfilePicture();
            $user->milestone = (int)Carbon::parse($user->date_of_joining)->diffInYears(now()) + 1;
            return $user;
        });

        return response()->json([
            'isMyBirthday' => $isMyBirthday,
            'unreadWishes' => $unreadWishes,
            'colleagues' => $colleaguesBirthdays,
            'isMyAnniversary' => $isMyAnniversary,
            'unreadAnniversaryWishes' => $unreadAnniversaryWishes,
            'colleagueAnniversaries' => $colleaguesAnniversaries
        ]);
    }

    /**
     * Send a celebration wish to a colleague (Handles both Birthday and Work Anniversary).
     */
    public function sendWish(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
            'wish_type' => 'required|in:birthday,anniversary',
        ]);

        $receiver = User::findOrFail($request->receiver_id);
        $sender = auth()->user();
        $today = Carbon::today();

        if ($request->wish_type === 'birthday') {
            if ($receiver->dob) {
                $dob = Carbon::parse($receiver->dob);
                if ($dob->month != $today->month || $dob->day != $today->day) {
                    return response()->json(['error' => 'It is not this user\'s birthday today.'], 400);
                }
            } else {
                return response()->json(['error' => 'This user has no birthday set.'], 400);
            }

            try {
                $receiver->notify(new BirthdayWishNotification($sender, $request->message));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Birthday wish notification error: ' . $e->getMessage());
            }
        } else {
            if ($receiver->date_of_joining) {
                $doj = Carbon::parse($receiver->date_of_joining);
                if ($doj->month != $today->month || $doj->day != $today->day) {
                    return response()->json(['error' => 'It is not this user\'s work anniversary today.'], 400);
                }
            } else {
                return response()->json(['error' => 'This user has no work joining date set.'], 400);
            }

            try {
                $receiver->notify(new \App\Notifications\WorkAnniversaryWishNotification($sender, $request->message));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Anniversary wish notification error: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'message' => 'Wish sent successfully!']);
    }

    /**
     * Mark the current user's unread celebration wishes as read.
     */
    public function markWishesRead(Request $request)
    {
        $currentUser = auth()->user();
        
        $currentUser->unreadNotifications()
            ->whereIn('type', [BirthdayWishNotification::class, \App\Notifications\WorkAnniversaryWishNotification::class])
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
