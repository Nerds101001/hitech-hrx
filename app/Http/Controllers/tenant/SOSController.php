<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SOSLog;
use App\Http\Middleware\SOSAddonCheck;

class SOSController extends Controller
{
  public function __construct()
  {
    $this->middleware(SOSAddonCheck::class);
  }

  public function index()
  {
    $totalRequests = SOSLog::count();
    $pendingRequests = SOSLog::where('status', 'pending')->count();
    $resolvedRequests = SOSLog::where('status', 'resolved')->count();

    return view('tenant.sos.map', compact('totalRequests', 'pendingRequests', 'resolvedRequests'));
  }

  public function fetchSOSRequests()
  {
    $sosLogs = SOSLog::with('user')
      ->where('status', 'pending')
      ->get();

    $data = $sosLogs->map(function ($log) {
      return [
        'id' => $log->id,
        'latitude' => $log->latitude,
        'longitude' => $log->longitude,
        'name' => $log->user->getFullName() ?? 'Unknown',
        'address' => $log->address,
        'notes' => $log->notes,
        'img_url' => $log->img_url,
        'created_at' => $log->created_at->format('Y-m-d H:i'),
      ];
    });

    return response()->json($data);
  }

  public function markAsResolved($id)
  {
    $sosLog = SOSLog::findOrFail($id);
    $sosLog->status = 'resolved';
    $sosLog->resolved_by_id = auth()->id();
    $sosLog->resolved_at = now();
    $sosLog->save();

    return response()->json(['success' => true, 'message' => 'SOS marked as resolved']);
  }
}
