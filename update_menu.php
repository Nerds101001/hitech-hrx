<?php

$json = file_get_contents('d:/live_server/resources/menu/tenantVerticalMenu.json');
$data = json_decode($json, true);

function addAccountsRole(&$item) {
    if (isset($item['roles']) && is_array($item['roles'])) {
        if (in_array('employee', $item['roles']) && !in_array('accounts', $item['roles'])) {
            $item['roles'][] = 'accounts';
        }
    }
    
    if (isset($item['submenu']) && is_array($item['submenu'])) {
        foreach ($item['submenu'] as &$sub) {
            addAccountsRole($sub);
        }
    }
}

foreach ($data['menu'] as &$item) {
    addAccountsRole($item);
}

file_put_contents('d:/live_server/resources/menu/tenantVerticalMenu.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Updated tenantVerticalMenu.json\n";
