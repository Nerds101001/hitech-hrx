<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatFile;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
use App\Models\ChatMessageReadReceipt;
use App\Models\ChatParticipant;
use App\Notifications\Chat\NewChatMessage;
use Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{

  public function getOneToOneChat($userId)
  {
    $authId = auth()->id();

    $chat = Chat::whereHas('participants', function ($query) use ($authId, $userId) {
      $query->where('user_id', $authId);
    })->whereHas('participants', function ($query) use ($authId, $userId) {
      $query->where('user_id', $userId);
    })->first();

    return Success::response([
      'id' => $chat->id,
      'userId' => $chat->name ? null : $chat->participants->where('user_id', '!=', $authId)->first()->user_id,
      'avatar' => $chat->name ? null : $chat->participants->where('user_id', '!=', $authId)->first()->user->getProfilePicture(),
      'name' => $chat->name ?? $chat->participants->where('user_id', '!=', $authId)->first()->user->getFullName(),
      'lastMessage' => '',
      'updatedAt' => $chat->updated_at->format(Constants::DateTimeFormat),
    ]);
  }

  // Get all chats for the authenticated user
  public function getChats()
  {
    $userId = auth()->id();

    $chats = Chat::whereHas('participants', function ($query) use ($userId) {
      $query->where('user_id', $userId);
    })
      ->with(['participants.user', 'messages' => function ($query) {
        $query->latest()->limit(1);
      }])
      ->get();

    $chats = $chats->map(function ($chat) use ($userId) {
      $lastMessage = $chat->messages->first();
      $lastMessageContent = $lastMessage ? $lastMessage->content : 'No Messages';
      $lastMessageType = $lastMessage ? $lastMessage->message_type : 'text';
      return [
        'id' => $chat->id,
        'name' => $chat->name ?? $chat->participants->where('user_id', '!=', $userId)->first()->user->getFullName(),
        'avatar' => $chat->name ? null : $chat->participants->where('user_id', '!=', $userId)->first()->user->getProfilePicture(),
        'lastMessage' => $lastMessageType != 'text' ? 'Sent a ' . $lastMessageType : $lastMessageContent,
        'userId' => $chat->name ? null : $chat->participants->where('user_id', '!=', $userId)->first()->user_id,
        'updatedAt' => $lastMessage ? $lastMessage->created_at->diffForHumans() : $chat->created_at->diffForHumans(),
        'isGroupChat' => $chat->is_group_chat,
      ];
    });

    return Success::response($chats);
  }

  public function createChat(Request $request)
  {
    $request->validate([
      'name' => 'required_if:is_group_chat,true',
      'isGroupChat' => 'required|boolean',
      'participants' => 'required|array|min:1',
    ]);

    $authId = auth()->id();

    //Check if chat already exists
    if (!$request->isGroupChat) {
      $chat = Chat::whereHas('participants', function ($query) use ($authId) {
        $query->where('user_id', $authId);
      })->whereHas('participants', function ($query) use ($request) {
        $query->where('user_id', $request->participants[0]);
      })->first();

      if ($chat) {
        return Error::response('Chat already exists');
      }
    }

    $chat = Chat::create([
      'name' => $request->name,
      'is_group_chat' => $request->isGroupChat,
      'created_by_id' => $authId,
    ]);

    // Add participants
    foreach ($request->participants as $userId) {
      ChatParticipant::create([
        'chat_id' => $chat->id,
        'user_id' => $userId,
        'created_by_id' => $authId,
      ]);
    }

    ChatParticipant::create([
      'chat_id' => $chat->id,
      'user_id' => $authId,
      'created_by_id' => $authId,
    ]);

    $response = [
      'id' => $chat->id,
      'name' => $chat->name ?? $chat->participants->where('user_id', '!=', $authId)->first()->user->getFullName(),
      'lastMessage' => '',
      'updatedAt' => $chat->updated_at->format(Constants::DateTimeFormat),
    ];

    return Success::response($response);
  }

  // Create a new chat (group or one-on-one)

  public function getChatMessages(Request $request)
  {
    $skip = $request->skip;
    $take = $request->take ?? 10;

    $messages = ChatMessage::query()
      ->where('chat_id', $request->chatId)
      ->with(['user', 'reactions', 'readReceipts', 'chatFile'])
      ->orderBy('created_at', 'desc');

    $totalCount = $messages->count();

    $messages = $messages->skip($skip)->take($take)->get();

    $result = $messages->map(function ($message) {
      $reactions = $message->reactions->map(function ($reaction) {
        return [
          'userId' => $reaction->user_id,
          'reaction' => $reaction->reaction,
        ];
      });

      $readReceipts = $message->readReceipts->map(function ($readReceipt) {
        return [
          'userId' => $readReceipt->user_id,
          'readAt' => $readReceipt->read_at->format(Constants::DateTimeFormat),
        ];
      });

      return [
        'id' => $message->id,
        'content' => $message->content,
        'messageType' => $message->message_type,
        'userId' => $message->user_id,
        'userName' => $message->user->getFullName(),
        'reactions' => $reactions,
        'readReceipts' => $readReceipts,
        'createdAt' => $message->createdAt(),
        'createdAtHuman' => $message->created_at->diffForHumans(),
        'time' => $message->created_at->format(Constants::TimeFormat),
        'file' => $message->chatFile ? [
          'filePath' => asset('storage/' . Constants::BaseFolderChatFiles . $message->chatFile->file_path),
          'fileType' => $message->chatFile->file_type,
          'fileExtension' => $message->chatFile->file_extension,
          'fileSize' => $message->chatFile->file_size,
          'fileName' => $message->chatFile->file_name,
        ] : null,
      ];
    });

    $response = [
      'totalCount' => $totalCount,
      'values' => $result,
    ];

    return Success::response($response);
  }

  // Fetch chat messages

  public function getNewChatMessages(Request $request)
  {
    $chatId = $request->chatId;
    $lastMessageId = $request->lastMessageId;

    $query = ChatMessage::where('chat_id', $chatId);

    if ($lastMessageId) {
      $query->where('id', '>', $lastMessageId);
    }

    $messages = $query->orderBy('created_at', 'asc')->get();

    $result = $messages->map(function ($message) {
      $reactions = $message->reactions->map(function ($reaction) {
        return [
          'userId' => $reaction->user_id,
          'reaction' => $reaction->reaction,
        ];
      });

      $readReceipts = $message->readReceipts->map(function ($readReceipt) {
        return [
          'userId' => $readReceipt->user_id,
          'readAt' => $readReceipt->read_at->format(Constants::DateTimeFormat),
        ];
      });

      return [
        'id' => $message->id,
        'content' => $message->content,
        'messageType' => $message->message_type,
        'userId' => $message->user_id,
        'userName' => $message->user->getFullName(),
        'reactions' => $reactions,
        'readReceipts' => $readReceipts,
        'createdAt' => $message->createdAt(),
        'createdAtHuman' => $message->created_at->diffForHumans(),
        'time' => $message->created_at->format(Constants::TimeFormat),
        'file' => $message->chatFile ? [
          'filePath' => asset('storage/' . Constants::BaseFolderChatFiles . $message->chatFile->file_path),
          'fileType' => $message->chatFile->file_type,
          'fileExtension' => $message->chatFile->file_extension,
          'fileSize' => $message->chatFile->file_size,
          'fileName' => $message->chatFile->file_name,
        ] : null,
      ];
    });

    return Success::response($result);
  }

  public function sendMessage(Request $request, $chatId)
  {
    $request->validate([
      'message' => 'required|string',
      'messageType' => 'string|in:text,location,contact',
    ]);

    $message = ChatMessage::create([
      'chat_id' => $chatId,
      'user_id' => auth()->id(),
      'content' => $request->message,
      'message_type' => $request->messageType ?? 'text',
    ]);

    $msg = $message->content;
    if ($request->messageType == 'location') {
      $msg = 'Shared a location';
    } else if ($request->messageType == 'contact') {
      $msg = 'Shared a contact';
    }

    Notification::send($message->chat->participants->where('user_id', '!=', auth()->id())->pluck('user'), new NewChatMessage('New message from ' . auth()->user()->getFullName(), $msg));

    return Success::response($message->id);
  }

  // Send a message

  public function sendFile(Request $request, $chatId)
  {
    $request->validate([
      'file' => 'required|file|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,apk,m4a,mp3,mp4,flv,avi,wmv,mov,mpg,mkv,webm,xls,xlsx,ppt,pptx|max:50000',
      'type' => 'required|string|in:file,image,audio,gif,video,document,archive,spreadsheet,presentation,txt,pdf,apk',
    ]);

    $file = $request->file('file');

    $fileName = time() . '_' . $file->getClientOriginalName();

    Storage::disk('public')->putFileAs(Constants::BaseFolderChatFiles, $file, $fileName);

    $message = ChatMessage::create([
      'chat_id' => $chatId,
      'user_id' => auth()->id(),
      'message_type' => $request->type,
    ]);


    ChatFile::create([
      'chat_id' => $chatId,
      'uploaded_by_id' => auth()->id(),
      'file_path' => $fileName,
      'file_type' => $request->type,
      'file_extension' => $file->extension(),
      'file_name' => $file->getClientOriginalName(),
      'file_size' => $file->getSize(),
      'chat_message_id' => $message->id
    ]);

    $msg = 'Shared a ' . $request->type;

    Notification::send($message->chat->participants->where('user_id', '!=', auth()->id())->pluck('user'), new NewChatMessage('New message from ' . auth()->user()->getFullName(), $msg));

    return Success::response($message->id);
  }


  public function forwardFile(Request $request, $chatId)
  {
    $request->validate([
      'messageId' => 'required|exists:chat_messages,id',
    ]);

    $chatMessage = ChatMessage::find($request->messageId);

    $file = ChatFile::where('chat_message_id', $chatMessage->id)->first();

    $message = ChatMessage::create([
      'chat_id' => $chatId,
      'user_id' => auth()->id(),
      'is_forwarded' => true,
      'message_type' => $file->file_type,
    ]);

    ChatFile::create([
      'chat_id' => $chatId,
      'uploaded_by_id' => auth()->id(),
      'file_path' => $file->file_path,
      'file_type' => $file->file_type,
      'file_extension' => $file->file_extension,
      'file_name' => $file->file_name,
      'file_size' => $file->file_size,
      'chat_message_id' => $message->id
    ]);

    $msg = 'Shared a ' . $file->file_type;

    Notification::send($message->chat->participants->where('user_id', '!=', auth()->id())->pluck('user'), new NewChatMessage('New message from ' . auth()->user()->getFullName(), $msg));

    return Success::response($message->id);
  }

  public function markAsRead($messageId)
  {
    ChatMessageReadReceipt::updateOrCreate(
      ['chat_message_id' => $messageId, 'user_id' => auth()->id()],
      ['read_at' => now()]
    );

    return Success::response('Message marked as read');
  }

  // Mark a message as read

  public function addReaction(Request $request, $messageId)
  {
    $request->validate(['reaction' => 'required|string']);

    ChatMessageReaction::updateOrCreate(
      ['chat_message_id' => $messageId, 'user_id' => auth()->id()],
      ['reaction' => $request->reaction]
    );

    return Success::response('Reaction added successfully');
  }

  // Add reaction to a message

  public function addParticipant(Request $request, $chatId)
  {
    $request->validate(['userId' => 'required|exists:users,id']);

    ChatParticipant::create([
      'chat_id' => $chatId,
      'user_id' => $request->userId,
      'created_by_id' => auth()->id(),
    ]);

    return Success::response('Participant added successfully');
  }

  // Add participant to a group chat

  public function uploadFile(Request $request, $chat_id)
  {
    $request->validate([
      'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
    ]);

    $file = $request->file('file');
    $filePath = $file->store('chat_files', 'public');

    ChatFile::create([
      'chat_id' => $chat_id,
      'uploaded_by_id' => auth()->id(),
      'file_path' => $filePath,
      'file_type' => $file->extension(),
    ]);

    return Success::response(['filePath' => $filePath]);
  }

  // Upload file

  public function getParticipants($chatId)
  {
    $participants = ChatParticipant::where('chat_id', $chatId)
      ->with('user')
      ->get();

    $participants = $participants->map(function ($participant) {
      return [
        'id' => $participant->user->id,
        'firstName' => $participant->user->first_name,
        'lastName' => $participant->user->last_name,
        'code' => $participant->user->code,
        'avatar' => $participant->user->getProfilePicture(),
        'designation' => $participant->user->designation ? $participant->user->designation->name : 'N/A',
      ];
    });

    return Success::response($participants);
  }

}
