<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\ChatMessageReadReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
  public function getChats(): JsonResponse
  {
    $user = Auth::user();
    $chats = Chat::whereHas('participants', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })->with(['participants'])->get();

    return Success::response($chats);

  }


  public function sendMessage(Request $request, $chatId): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'message' => 'required|string',
      'messageType' => 'required|string|in:text,image,file',
    ]);

    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
    }

    $user = Auth::user();
    $chat = Chat::findOrFail($chatId);

    // Ensure user is a participant
    if (!$chat->participants()->where('user_id', $user->id)->exists()) {
      return Error::response('Unauthorized', 403);
    }

    $message = ChatMessage::create([
      'chat_id' => $chat->id,
      'user_id' => $user->id,
      'content' => $request->message,
      'message_type' => $request->messageType,
    ]);

    // WebRTC notification (client-side WebRTC connection handles messaging)
    $this->sendWebRTCSignal($message, $chat);

    return Success::response($message, 201);

  }

  public function create(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'is_group_chat' => 'required|boolean',
      'name' => 'required_if:is_group_chat,true|string|max:255',
      'participants' => 'required|array|min:2', // Include both users for one-on-one
      'participants.*' => 'exists:users,id'
    ]);

    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
    }


    $user = Auth::user();
    $chat = Chat::create([
      'is_group_chat' => $request->is_group_chat,
      'name' => $request->is_group_chat ? $request->name : null,
      'created_by' => $user->id,
    ]);

    foreach ($request->participants as $participant) {
      ChatParticipant::create([
        'chat_id' => $chat->id,
        'user_id' => $participant,
      ]);
    }

    return Success::response($chat, 201);

  }


  //TODO: Web RTC Signal
  private function sendWebRTCSignal($message, $chat)
  {

  }


  public function addParticipant(Request $request, $chatId): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'user_id' => 'required|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
    }

    $user = Auth::user();
    $chat = Chat::findOrFail($chatId);

    // Check permissions
    if (!$chat->is_group_chat || $chat->created_by_id !== $user->id) {
      return Error::response('Unauthorized to perform this action!', 403);
    }

    $participant = ChatParticipant::create([
      'chat_id' => $chat->id,
      'user_id' => $request->user_id,
    ]);

    return Success::response($participant, 201);

  }

  // Mark a message as read

  public function addReaction(Request $request, $messageId): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'reaction' => 'required|string',
    ]);

    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
    }


    $user = Auth::user();
    $message = ChatMessage::findOrFail($messageId);

    $reaction = ChatMessageReaction::updateOrCreate(
      ['chat_message_id' => $message->id, 'user_id' => $user->id],
      ['reaction' => $request->reaction]
    );

    return response()->json(['success' => true, 'data' => $reaction], 201);

  }

  public function markAsRead($messageId): JsonResponse
  {
    $user = Auth::user();
    $message = ChatMessage::findOrFail($messageId);

    ChatMessageReadReceipt::updateOrCreate(
      ['chat_message_id' => $message->id, 'user_id' => $user->id],
      ['read_at' => now()]
    );

    return Success::response('Message marked as read');
  }
}
