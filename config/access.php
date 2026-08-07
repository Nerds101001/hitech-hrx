<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Super delegate accounts
  |--------------------------------------------------------------------------
  |
  | Normal delegate access is a per-account pointer (users.access_delegate_id)
  | set for vacant org-chart positions. The accounts listed here bypass that
  | pointer: from the Employee Directory they may enter ANY active account in
  | one click, for support/debugging. Everything else about the delegate
  | session is unchanged — the original id is held in the session and the
  | header hub's "Exit Delegate Login" restores it.
  |
  | Comma-separated in SUPER_DELEGATE_EMAILS to override without a code change.
  |
  */

  'super_delegate_emails' => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('SUPER_DELEGATE_EMAILS', 'ai@rustx.com'))
  ))),

];
