<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Services\CommonService\SettingsService\ISettings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class DocumentTypeController extends Controller
{
  private ISettings $settings;

  public function __construct(ISettings $settings)
  {
    $this->settings = $settings;
  }

  public function index()
  {
    $total = DocumentType::count();
    $active = DocumentType::where('status', 'active')->count();
    $inactive = DocumentType::where('status', 'inactive')->count();

    return view('tenant.documentTypes.index', [
      'stats' => [
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive,
      ],
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function getListAjax(Request $request)
  {
    try {
      $query = DocumentType::query();

      if ($request->has('statusFilter') && !in_array($request->statusFilter, ['All', ''])) {
        $query->where('status', $request->statusFilter);
      }

      if ($request->has('searchTerm') && !empty($request->searchTerm)) {
        $search = $request->searchTerm;
        $query->where(function ($q) use ($search) {
          $q->where('name', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%")
            ->orWhere('notes', 'like', "%{$search}%");
        });
      }

      return DataTables::of($query)
        ->addColumn('status_display', function ($row) {
          $checked = $row->status === Status::ACTIVE ? 'checked' : '';
          return '
            <div class="d-flex justify-content-center">
              <label class="switch mb-0">
                <input type="checkbox" class="switch-input status-toggle" id="statusToggle' . $row->id . '" data-id="' . $row->id . '" ' . $checked . ' />
                <span class="switch-toggle-slider">
                  <span class="switch-on"><i class="bx bx-check"></i></span>
                  <span class="switch-off"><i class="bx bx-x"></i></span>
                </span>
              </label>
            </div>';
        })
        ->addColumn('actions', function ($row) {
          return '
            <div class="d-flex align-items-center justify-content-center gap-2">
              <a href="javascript:;" class="text-hitech edit-record me-2" data-id="' . $row->id . '" data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddOrUpdateDocumentType" title="Edit"><i class="bx bx-edit fs-4"></i></a>
              <a href="javascript:;" class="text-danger delete-record" data-id="' . $row->id . '" title="Delete"><i class="bx bx-trash fs-4"></i></a>
            </div>';
        })
        ->rawColumns(['status_display', 'actions'])
        ->make(true);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response($e->getMessage());
    }
  }

  public function getCodeAjax()
  {
    return response()->json($this->getCode());
  }

  private function getCode()
  {
    $prefix = $this->settings->getDocumentTypePrefix();

    $proofId = DocumentType::withTrashed()->max('id');
    $proofId += 1;
    $proofId = str_pad($proofId, 4, "0", STR_PAD_LEFT);

    $fullCode = "{$prefix}-{$proofId}";

    return $fullCode;
  }


  public function addOrUpdateAjax(Request $request)
  {
    $proofTypeId = $request->id;
    $request->validate([
      'name' => 'required',
      'notes' => 'nullable',
      'code' => 'required',
      'isRequired' => 'required',

    ]);

    try {

      if ($proofTypeId) {
        $proofType = DocumentType::find($proofTypeId);
        $proofType->name = $request->name;
        $proofType->notes = $request->notes;
        $proofType->code = $request->code;
        $proofType->is_required = $request->isRequired;
        $proofType->save();

        return Success::response('Updated');
      } else {

        $proofType = new DocumentType();
        $proofType->name = $request->name;
        $proofType->notes = $request->notes;
        $proofType->code = $request->code;
        $proofType->is_required = $request->isRequired;

        $proofType->save();

        return Success::response('Added');
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong. Please try again later');
    }
  }

  public function getByIdAjax($id)
  {
    $proofType = DocumentType::findOrFail($id);

    if (!$proofType) {
      return Error::response('Proof type not found');
    }

    $response = [
      'id' => $proofType->id,
      'name' => $proofType->name,
      'code' => $proofType->code,
      'notes' => $proofType->notes,
      'isRequired' => $proofType->is_required

    ];

    return Success::response($response);
  }

  public function deleteAjax($id)
  {
    $proofType = DocumentType::findOrFail($id);
    if (!$proofType) {
      return Error::response('Proof type not found');
    }

    $proofType->delete();
    return Success::response('Proof type deleted successfully');
  }

  public function changeStatusAjax($id)
  {
    $proofType = DocumentType::findOrFail($id);

    try {

      if (!$proofType) {
        return Error::response('Proof type not found');
      }
      $proofType->status = $proofType->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;

      $proofType->save();

      return Success::response('Proof type status changed successfully');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong. Please try again later');
    }
  }
}
