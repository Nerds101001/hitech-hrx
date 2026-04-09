<?php

namespace App\Http\Controllers\Api;

use App\ApiClasses\Success;
use App\Enums\CallStatus;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use TaylanUnutmaz\AgoraTokenBuilder\RtcTokenBuilder;

class CallController extends Controller
{
  // Initiate a call
  public function initiateCall(Request $request)
  {
    /* $request->validate([
       'callType' => 'required|in:audio,video',
       'receiverId' => 'required|exists:users,id',
     ]);*/

    $channelId = Str::uuid(); // Generate a unique Agora channel ID
    /*
        $callLog = CallLog::create([
          'channel_id' => $channelId,
          'call_type' => CallLogType::from($request->callType),
          'initiated_by_id' => auth()->id(),
          'start_time' => now(),
          'status' => CallStatus::MISSED
        ]);*/

    $rtcToken = $this->generateAgoraToken($channelId);
    return Success::response([
      'channelId' => $channelId,
      'callType' => 'audio',
      'token' => $rtcToken,
    ]);
  }

  // Complete a call
  public function completeCall(Request $request, $channelId)
  {
    $callLog = CallLog::where('channel_id', $channelId)->firstOrFail();

    $callLog->update([
      'end_time' => now(),
      'status' => CallStatus::COMPLETED,
      'duration' => Carbon::parse($callLog->start_time)->diffInSeconds(now()),
    ]);

    return Success::response('Call completed successfully');
  }

  // Check call status
  public function getCallStatus($channelId)
  {
    $callLog = CallLog::where('channel_id', $channelId)->firstOrFail();

    return Success::response([
      'status' => $callLog->status,
      'duration' => $callLog->duration,
    ]);
  }

  public function testToken(Request $request)
  {
    $request->validate([
      'callType' => 'required|in:audio,video',
      'receiverId' => 'required|exists:users,id',
    ]);
    $channelId = Str::uuid();

    $rtcToken = $this->generateAgoraToken('test-token');

    return Success::response([
      'channelId' => $channelId,
      'callType' => $request->callType,
      'token' => $rtcToken,
    ]);
  }

  private function generateAgoraToken($channelId)
  {
    $appId = '4c441fd4fc534784a443beb70656be33';
    $appCertificate = 'bdf05e3075284e4195602007b49c0d26';
    $channelName = $channelId;
    $uid = rand(1, 230);
    $expirationTimeInSeconds = 86400;
    $currentTimeStamp = time();
    $privilegeExpiredTs = $currentTimeStamp + $expirationTimeInSeconds;

    if (empty($appId) || empty($appCertificate)) {
      throw new \Exception("Agora App ID or Certificate is missing in environment variables");
    }

    if (empty($channelId)) {
      throw new \Exception("Channel name cannot be empty");
    }

    return RtcTokenBuilder::buildTokenWithUid($appId, $appCertificate, $channelName, $uid, RtcTokenBuilder::RolePublisher, $privilegeExpiredTs);
  }
}
