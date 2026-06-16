
\ = 'https://api-crm.rustx.net/api/v1/auth/login';
\ = ['email' => 'Nagender', 'password' => 'Nag@8745'];
\ = curl_init(\);
curl_setopt(\, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\, CURLOPT_POSTFIELDS, json_encode(\));
curl_setopt(\, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
\ = curl_exec(\);
\ = json_decode(\, true)['token'] ?? '';
if(!\) { echo 'Login failed'; exit; }

\ = 'https://api-crm.rustx.net/api/v1/auth/employee-list';
\ = curl_init(\);
curl_setopt(\, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.\, 'Accept: application/json']);
\ = curl_exec(\);
\ = json_decode(\, true)['users'] ?? [];
\ = [];
foreach(\ as \) {
    if(stripos(\['USER_NAME'] ?? '', 'vaneet') !== false || stripos(\['EMAIL'] ?? '', 'vaneet') !== false || stripos(\['EMAIL'] ?? '', 'ppc5') !== false || stripos(\['USER_NAME'] ?? '', 'ppc5') !== false) {
        \[] = (\['USER_ID']??'').' | '.(\['USER_NAME']??'').' | '.(\['EMAIL']??'').' | '.(\['WORKING']??'');
    }
}
if(empty(\)) { echo 'Not found in CRM.'; } else { print_r(\); }
