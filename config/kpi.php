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
  | 568 GM-North, 585 Hyderabad, 597 Manpreet Walia office (2026-09-30).
  |
  | 2026-10-08: emptied on explicit request — every active person in Sales and
  | Production is to carry the KPIs regardless of designation, including these
  | three. Restore the ids here to exclude them again.
  |
  | 2026-10-13: 476 Jasbir Kaur (New Biz only, in the combined CCare department)
  | and 600 Demo CCARE are excluded so the CCare scoring KPIs skip them.
  |
  */

  'excluded_user_ids' => [476, 600],

];
