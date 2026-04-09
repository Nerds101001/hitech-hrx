<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TaskController extends Controller
{
    /**
     * Store a newly created task in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'userId' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date|after_or_equal:today',
            'type' => 'required|in:open,site_based,client_based',
        ]);

        try {
            Task::create([
                'user_id' => $validated['userId'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'due_date' => $validated['due_date'],
                'type' => $validated['type'],
                'assigned_by_id' => auth()->id(),
                'status' => 'new',
                'for_date' => now(), // Required field in migration
                'created_by_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Task assigned successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to assign task: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date|after_or_equal:today',
            'type' => 'required|in:open,site_based,client_based',
        ]);

        try {
            $task = Task::findOrFail($id);
            $task->title = $validated['title'];
            $task->description = $validated['description'];
            $task->due_date = $validated['due_date'];
            $task->type = $validated['type'];
            $task->updated_by_id = auth()->id();
            $task->save();

            return response()->json(['success' => true, 'message' => 'Task updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update task: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of the specified task.
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,in_progress,completed,cancelled,hold,rejected,reassigned,reopened,resolved,closed',
        ]);

        try {
            $task = Task::findOrFail($id);
            $task->status = $validated['status'];
            $task->save();

            return response()->json(['success' => true, 'message' => 'Task status updated to ' . $validated['status']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update task status.'], 500);
        }
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy($id)
    {
        try {
            $task = Task::findOrFail($id);
            $task->delete();

            return response()->json(['success' => true, 'message' => 'Task deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete task.'], 500);
        }
    }
}
