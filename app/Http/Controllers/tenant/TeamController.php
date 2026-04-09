<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Models\Team;
use Exception;
use Illuminate\Http\Request;

class TeamController extends Controller
{

  public function getTeamListAjax()
  {
    $teams = Team::where('status', Status::ACTIVE)
      ->get(['id', 'name', 'code']);
    return Success::response($teams);
  }

  public function index()
  {
    return view('tenant.teams.index', [
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function getTeamsListAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'name',
        3 => 'notes',
        4 => 'code',
        5 => 'status',
      ];

      $search = [];

      $totalData = Team::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      if (empty($request->input('search.value'))) {
        $teams = Team::offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');
        $teams = Team::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->get();

        $totalFiltered = Team::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->count();
      }

      $data = [];
      if (!empty($teams)) {
        foreach ($teams as $team) {
          $nestedData['id'] = $team->id;
          $nestedData['name'] = $team->name;
          $nestedData['code'] = $team->code;
          $nestedData['notes'] = $team->notes;
          $nestedData['status'] = $team->status;
          $nestedData['action'] = '<div class="d-flex align-items-center justify-content-center gap-2">' .
            '<a href="javascript:;" class="text-hitech edit-record me-2" data-id="' . $team->id . '" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateTeam" title="Edit"><i class="bx bx-edit fs-4"></i></a>' .
            '<a href="javascript:;" class="text-danger delete-record" data-id="' . $team->id . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>' .
            '</div>';
          $data[] = $nestedData;
        }
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'code' => 200,
        'data' => $data
      ]);
    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }
  }


  public function addOrUpdateTeamAjax(Request $request)
  {
    $teamId = $request->id;
    $request->validate([
      'name' => 'required',
      'code' => ['required', 'unique:teams,code,' . $teamId],
      'notes' => 'nullable',
      'isChatEnabled' => 'required',
      'manager_ids' => 'nullable|array',
      'manager_ids.*' => 'exists:users,id'
    ]);

    if ($teamId) {
      $team = Team::find($teamId);
      $team->name = $request->name;
      $team->notes = $request->notes;
      $team->code = $request->code;
      $team->is_chat_enabled = $request->isChatEnabled;
      $team->save();
      
      $team->managers()->sync($request->manager_ids ?? []);

      return response()->json([
        'code' => 200,
        'message' => 'Updated',
      ]);
    } else {

      $team = new Team();
      $team->name = $request->name;
      $team->notes = $request->notes;
      $team->code = $request->code;
      $team->is_chat_enabled = $request->isChatEnabled;
      $team->save();

      $team->managers()->sync($request->manager_ids ?? []);

      return response()->json([
        'code' => 200,
        'message' => 'Added',
      ]);
    }
  }
  public function checkCodeValidationAjax(Request $request)
  {
    $code = $request->code;


    if (!$code) {
      return response()->json(["valid" => false]);
    }

    if ($request->has('id')) {
      $id = $request->input('id');
      if (Team::where('code', $code)->where('id', '!=', $id)->exists()) {
        return response()->json([
          "valid" => false,
        ]);
      } else {
        return response()->json([
          "valid" => true,
        ]);
      }
    }
    if (Team::where('code', $code)->exists()) {
      return response()->json([
        "valid" => false,
      ]);
    }
    return response()->json([
      "valid" => true,
    ]);
  }

  public function getTeamAjax($id)
  {
    $team = Team::with('managers')->findOrFail($id);

    if (!$team) {
      return Error::response('Team not found');
    }
    $response = [
      'id' => $team->id,
      'name' => $team->name,
      'code' => $team->code,
      'notes' => $team->notes,
      'isChatEnabled' => $team->is_chat_enabled,
      'manager_ids' => $team->managers->pluck('id')->toArray(),
    ];

    return response()->json($response);
  }

  public function deleteTeamAjax($id)
  {
    $team = Team::findOrFail($id);
    if (!$team) {
      return Error::response('Team not found');
    }

    $team->delete();
    return Success::response('Team deleted successfully');
  }

  public function changeStatus($id)
  {
    $team = Team::findOrFail($id);

    if (!$team) {
      return response()->json([
        'code' => 404,
        'message' => 'Team not found',
      ]);
    }
    $team->status = $team->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $team->save();
    return response()->json([
      'code' => 200,
      'message' => 'Team status changed successfully',
    ]);
  }
}
