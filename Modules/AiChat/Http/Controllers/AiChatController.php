<?php

namespace Modules\AiChat\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// SECURITY (H-8): the GptService/SchemaService text-to-SQL services -- which ran
// GPT-generated SQL directly against the whole database (cross-tenant read/write)
// -- have been DELETED, not just disabled, so they cannot be revived by
// uncommenting a route. This controller no longer depends on them; the remaining
// actions are hard-disabled below.

class AiChatController extends Controller
{
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
