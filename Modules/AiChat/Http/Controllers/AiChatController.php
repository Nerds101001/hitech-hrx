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

  public function handleQuery(Request $request)
  {
    try {
      $userQuery = $request->input('query');
      $response = $this->gptService->interpretQueryV2($userQuery);

      return response()->json([
        'success' => true,
        'response' => $response,
      ]);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'response' => $e->getMessage(),
      ]);
    }
  }

  public function test()
  {
    $result = DB::select('SELECT * FROM users');

    return response()->json([
      'success' => true,
      'response' => $result,
    ]);
  }

  public function getSchema()
  {
    return response()->json([
      'success' => true,
      'response' => $this->schemaService->getSchema(),
    ]);
  }
}
