<?php

namespace Modules\AiChat\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AiChat\Services\GptService;
use Modules\AiChat\Services\SchemaService;

class AiChatController extends Controller
{
  protected GptService $gptService;
  protected SchemaService $schemaService;

  public function __construct(GptService $gptService, SchemaService $schemaService)
  {
    $this->gptService = $gptService;
    $this->schemaService = $schemaService;
  }

  public function index()
  {
    return view('aichat::index');
  }

  // SECURITY (QA fix): these actions executed GPT-generated SQL directly against
  // the whole database (cross-tenant read/write) and exposed the users table and
  // full schema. Hard-disabled at the controller level as defense-in-depth in
  // addition to the routes being removed. Do NOT re-enable without a read-only,
  // SELECT-only, tenant-scoped, admin-gated redesign. See QA Report 6 (ADD-C1/C2).

  public function handleQuery(Request $request)
  {
    abort(403, 'This feature has been disabled for security reasons.');
  }

  public function test()
  {
    abort(403, 'This feature has been disabled for security reasons.');
  }

  public function getSchema()
  {
    abort(403, 'This feature has been disabled for security reasons.');
  }
}
