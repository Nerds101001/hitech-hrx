<?php

namespace App\Http\Controllers\tenant;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

// Assuming model path

// Use standard JsonResponse

// Use Auth facade

// Import DataTables facade

class ShiftController extends Controller
{
  /**
   * Display shifts management view.
   * Route: GET /shifts
   * Name: shifts.index
   */
  public function index()
  {
    $total = Shift::count();
    $active = Shift::where('status', 'active')->count();
    $inactive = Shift::where('status', 'inactive')->count();

    return view('tenant.shift.index', [
      'stats' => [
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive,
      ],
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function getActiveShiftsForDropdown(): JsonResponse
  {
    try {
      $shifts = Shift::where('status', Status::ACTIVE) // Ensure Status::ACTIVE is correctly defined
        ->select('id', 'name', 'code')
        ->orderBy('name')
        ->get();

      return response()->json(['success' => true, 'data' => $shifts]);
    } catch (Exception $e) {
      Log::error('Error fetching active shifts for dropdown: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to load shifts.'], 500);
    }
  }

  /**
   * Handle DataTables AJAX request for shifts.
   * Route: GET /shifts/list
   * Name: shifts.listAjax
   */
  public function listAjax(Request $request): JsonResponse
  {
    // abort_if_cannot('view_shifts');
    $query = Shift::query()->select('shifts.*'); // Select columns

    // Apply Search
    if ($request->filled('search.value')) {
      $searchValue = $request->input('search.value');
      $query->where(function ($q) use ($searchValue) {
        $q->where('name', 'LIKE', "%{$searchValue}%")
          ->orWhere('code', 'LIKE', "%{$searchValue}%")
          ->orWhere('notes', 'LIKE', "%{$searchValue}%");
      });
    }

    return DataTables::eloquent($query)
      ->addColumn('shift_days', function ($shift) {
        // Render the day badges (similar to JS render function)
        $daysHtml = '<div class="d-flex justify-content-start flex-wrap gap-1">'; // Use flex-wrap
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        foreach ($days as $day) {
          $label = ucfirst(substr($day, 0, 3));
          $class = $shift->$day ? 'bg-label-success' : 'bg-label-secondary';
          $daysHtml .= '<span class="badge ' . $class . '">' . $label . '</span>';
        }
        $daysHtml .= '</div>';
        return $daysHtml;
      })
      ->addColumn('status_display', function ($shift) { // Render status toggle server-side
        $isChecked = $shift->status == Status::ACTIVE ? 'checked' : '';
        $statusUrl = route('shifts.toggleStatus', $shift->id); // Use correct route name
        return '
          <div class="hitech-toggle-wrapper ms-2">
            <input class="hitech-switch-input shift-status-toggle" type="checkbox" 
              id="statusToggle' . $shift->id . '" data-url="' . $statusUrl . '" ' . $isChecked . '>
            <label class="hitech-switch-label" for="statusToggle' . $shift->id . '"></label>
          </div>';
      })
      ->addColumn('actions', function ($shift) {
        $editUrl = route('shifts.edit', $shift->id); // URL to fetch edit data
        $deleteUrl = route('shifts.destroy', $shift->id); // URL for delete action
        // Check if shift is assigned before allowing delete
        $isAssigned = User::where('shift_id', $shift->id)->exists(); // Check both tables if necessary

        $editButton = '<a href="javascript:;" class="text-hitech edit-shift me-2" data-id="' . $shift->id . '" data-url="' . $editUrl . '" title="Edit"><i class="bx bx-edit fs-4"></i></a>';
        $deleteButton = '<a href="javascript:;" class="text-danger delete-shift' . ($isAssigned ? ' opacity-50 cursor-not-allowed' : '') . '" data-id="' . $shift->id . '" data-url="' . $deleteUrl . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>';
        return '<div class="d-flex justify-content-center align-items-center">' . $editButton . $deleteButton . '</div>';
      })
      ->rawColumns(['shift_days', 'status_display', 'actions']) // Specify raw columns
      ->orderColumn('name', fn($q, $dir) => $q->orderBy('name', $dir)) // Example server-side ordering
      ->orderColumn('code', fn($q, $dir) => $q->orderBy('code', $dir))
      ->orderColumn('status', fn($q, $dir) => $q->orderBy('status', $dir))
      ->make(true);
  }


  /**
   * Validate shift data (helper method).
   */
  protected function validateShift(Request $request, $shiftId = null)
  {
    $tenantId = Auth::user()->tenant_id; // Assuming tenant context
    $rules = [
      'name' => 'required|string|max:191',
      'code' => [
        'required',
        'string',
        'max:50',
        Rule::unique('shifts', 'code')->where(fn($query) => $query->where('tenant_id', $tenantId))->ignore($shiftId)
      ],
      'notes' => 'nullable|string|max:500',
      'start_time' => 'required|date_format:H:i', // Store as HH:MM
      'end_time' => 'required|date_format:H:i', // Basic check, overnight handled elsewhere
      // Assuming checkboxes send '1' when checked, or not present if unchecked
      'sunday' => 'nullable|boolean',
      'monday' => 'nullable|boolean',
      'tuesday' => 'nullable|boolean',
      'wednesday' => 'nullable|boolean',
      'thursday' => 'nullable|boolean',
      'friday' => 'nullable|boolean',
      'saturday' => 'nullable|boolean',
      'is_flexible' => 'nullable|boolean',
      'flex_start_time' => 'nullable|required_if:is_flexible,1|date_format:H:i',
      'flex_end_time' => 'nullable|required_if:is_flexible,1|date_format:H:i',
      'min_working_hours' => 'nullable|numeric|min:0.5|max:24',
    ];
    // Prepare data (convert checkbox presence to boolean)
    $data = $request->all();
    foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'is_flexible'] as $day) {
      $data[$day] = filter_var($request->input($day, false), FILTER_VALIDATE_BOOLEAN);
    }
    // Handle start/end date if they are part of the form (they weren't in the JS provided)
    // $data['start_date'] = ...;
    // $data['end_date'] = ...;

    $validator = Validator::make($data, $rules);

    // Return validator instance AND prepared data
    return ['validator' => $validator, 'data' => $data];
  }

  /**
   * Store a newly created shift in storage.
   * Route: POST /shifts
   * Name: shifts.store
   */
  public function store(Request $request): JsonResponse
  {
    // abort_if_cannot('create_shifts');
    $validationResult = $this->validateShift($request);
    $validator = $validationResult['validator'];
    $validatedData = $validationResult['data'];


    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
    }

    try {
      // Set defaults not handled by form
      $validatedData['status'] = Status::ACTIVE; // Default to active
      $validatedData['start_date'] = now()->format('Y-m-d'); // Default start date? Or require in form?
      // Handle time format conversion if needed (Model cast should handle H:i:s)
      $validatedData['start_time'] = Carbon::parse($validatedData['start_time'])->format('H:i:s');
      $validatedData['end_time'] = Carbon::parse($validatedData['end_time'])->format('H:i:s');

      if ($validatedData['is_flexible']) {
        $validatedData['flex_start_time'] = Carbon::parse($validatedData['flex_start_time'])->format('H:i:s');
        $validatedData['flex_end_time'] = Carbon::parse($validatedData['flex_end_time'])->format('H:i:s');
      }

      $shift = Shift::create($validatedData);
      // tenant_id and user actions handled by traits

      Log::info("Shift created: ID {$shift->id}, Code {$shift->code} by User " . Auth::id());
      return response()->json(['success' => true, 'message' => 'Shift created successfully.', 'shift_id' => $shift->id], 201);
    } catch (Exception $e) {
      Log::error('Error creating shift: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to create shift.'], 500);
    }
  }


  /**
   * Fetch data for editing the specified shift.
   * Route: GET /shifts/{shift}/edit
   * Name: shifts.edit
   */
  public function edit(Shift $shift): JsonResponse // Use Route Model Binding
  {
    // abort_if_cannot('edit_shifts');
    // Add tenant check if needed

    // Format time for consistency with input type="time" or flatpickr time picker
    $shift->start_time_formatted = $shift->start_time ? $shift->start_time->format('H:i') : null;
    $shift->end_time_formatted = $shift->end_time ? $shift->end_time->format('H:i') : null;
    $shift->flex_start_time_formatted = $shift->flex_start_time ? Carbon::parse($shift->flex_start_time)->format('H:i') : null;
    $shift->flex_end_time_formatted = $shift->flex_end_time ? Carbon::parse($shift->flex_end_time)->format('H:i') : null;

    return response()->json(['success' => true, 'shift' => $shift]);
  }


  /**
   * Update the specified shift in storage.
   * Route: PUT /shifts/{shift}
   * Name: shifts.update
   */
  public function update(Request $request, Shift $shift): JsonResponse
  {
    // abort_if_cannot('edit_shifts');
    // Add tenant check if needed

    $validationResult = $this->validateShift($request, $shift->id); // Pass ID to ignore self in unique check
    $validator = $validationResult['validator'];
    $validatedData = $validationResult['data'];

    if ($validator->fails()) {
      return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
    }

    try {
      // Handle time format conversion if needed
      $validatedData['start_time'] = Carbon::parse($validatedData['start_time'])->format('H:i:s');
      $validatedData['end_time'] = Carbon::parse($validatedData['end_time'])->format('H:i:s');

      if ($validatedData['is_flexible']) {
        $validatedData['flex_start_time'] = Carbon::parse($validatedData['flex_start_time'])->format('H:i:s');
        $validatedData['flex_end_time'] = Carbon::parse($validatedData['flex_end_time'])->format('H:i:s');
      }

      $shift->update($validatedData);
      // updated_by_id handled by trait

      Log::info("Shift updated: ID {$shift->id}, Code {$shift->code} by User " . Auth::id());
      return response()->json(['success' => true, 'message' => 'Shift updated successfully.']);
    } catch (Exception $e) {
      Log::error("Error updating shift {$shift->id}: " . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to update shift.'], 500);
    }
  }

  /**
   * Toggle the active status of the specified shift.
   * Route: POST /shifts/{shift}/toggle-status
   * Name: shifts.toggleStatus
   */
  public function toggleStatus(Shift $shift): JsonResponse // Route model binding
  {
    // abort_if_cannot('edit_shifts');
    // Add tenant check if needed

    try {
      $newStatus = ($shift->status == Status::ACTIVE) ? Status::INACTIVE : Status::ACTIVE;
      $shift->status = $newStatus;
      $shift->save();

      Log::info("Shift status toggled: ID {$shift->id} to {$newStatus->value} by User " . Auth::id());
      return response()->json([
        'success' => true,
        'message' => 'Shift status updated successfully.',
        'newStatus' => $newStatus->value // Send back new status value
      ]);
    } catch (Exception $e) {
      Log::error("Error toggling status for shift {$shift->id}: " . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to update status.'], 500);
    }
  }


  /**
   * Remove the specified shift from storage (Soft Delete).
   * Route: DELETE /shifts/{shift}
   * Name: shifts.destroy
   */
  public function destroy(Shift $shift): JsonResponse
  {
    // abort_if_cannot('delete_shifts');
    // Add tenant check if needed

    try {
      // Check if shift is assigned to any active users
      $isAssigned = User::where('shift_id', $shift->id)->exists();
      if ($isAssigned) {
        return response()->json([
          'success' => false,
          'message' => 'Cannot delete shift: It is currently assigned to one or more users.'
        ], 409); // Conflict
      }

      $shiftId = $shift->id;
      $shiftCode = $shift->code;
      $shift->delete(); // Assumes SoftDeletes trait is used

      Log::info("Shift soft deleted: ID {$shiftId}, Code {$shiftCode} by User " . Auth::id());
      return response()->json(['success' => true, 'message' => 'Shift deleted successfully.']);
    } catch (Exception $e) {
      Log::error("Error deleting shift {$shift->id}: " . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to delete shift.'], 500);
    }
  }
}

// --- Remove or comment out old AJAX methods ---
// public function getShiftListAjax() { ... } // Replaced by listAjax with yajra
// public function getShiftsListAjax(Request $request) { ... } // Replaced by listAjax with yajra
// public function addOrUpdateShiftAjax(Request $request) { ... } // Replaced by store/update
// public function checkCodeValidationAjax(Request $request) { ... } // Keep if client-side validation uses it
// public function getShiftAjax($id) { ... } // Replaced by edit method
// public function deleteShiftAjax($id) { ... } // Replaced by destroy method
// public function changeStatus($id) { ... } // Replaced by toggleStatus method
