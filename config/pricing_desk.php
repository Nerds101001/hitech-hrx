<?php

/*
|--------------------------------------------------------------------------
| Sales Pricing Desk visibility defaults
|--------------------------------------------------------------------------
|
| Who sees the tool WITHOUT needing an individual grant: anyone whose
| department (whole-name match, not substring — same convention as
| hr_notifications.php's Finance-NOC departments) is in this list, plus
| admin/hr always.
|
| This whole page sits behind the Sales section's own outer gate
| (SalesAccessGuard), which already lets Sales/Customer Care/New Biz/Export
| departments through on a broader, substring-based department check of its
| own — so for those four this list is mostly redundant, not the only thing
| granting them access. Billing and AI & IT are NOT covered by that outer
| gate, though: a person there needs the `pricing_desk.view` permission
| actually granted (Role Permission Matrix or User Special Permissions) to
| get past it at all — being in this list alone will not show them the tool.
|
| Beyond this default, an admin can grant or revoke access per role (Role
| Permission Matrix) or per person (User Special Permissions) via the
| `pricing_desk.view` / `pricing_desk.manage` permissions — no code change
| needed for that. This list only sets the starting point.
*/

return [
    'visible_departments' => [
        'sales', 'sales department',
        'customer care', 'customer care + new biz',
        'new biz',
        'billing department',
        'ai & it',
    ],
];
