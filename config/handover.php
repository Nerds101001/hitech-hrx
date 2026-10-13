<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Who may see the Leaver Handover tool
  |--------------------------------------------------------------------------
  |
  | Hidden on purpose: only the people named here (comma separated in
  | HANDOVER_VIEWER_EMAILS) reach /sales-visits/leaver-handover, and only if they
  | are also Admin or HR. Everybody else gets a plain "not found", admins included.
  | An empty list lets nobody in, so a blank setting can never open it up.
  |
  */

  'viewer_emails' => (function () {
    $raw = trim((string) env('HANDOVER_VIEWER_EMAILS', ''));

    return array_values(array_filter(array_map('trim', explode(',', $raw === '' ? 'ai@rustx.com' : $raw))));
  })(),

];
