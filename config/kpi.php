<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Accounts that never get KPIs
  |--------------------------------------------------------------------------
  |
  | Office / branch accounts that sit in the Sales department (people report
  | to them, customers are booked to them) but are not a person to score.
  | KPI templates skip them - editing a template cannot hand them KPIs again -
  | and the scorecard pages leave them out.
  |
  | 568 GM-North, 585 Hyderabad (2026-09-30).
  |
  */

  'excluded_user_ids' => [568, 585],

];
