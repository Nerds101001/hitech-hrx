#!/usr/bin/env bash
#
# Deploys the Kingo Bingo Fill Targets Excel/PDF export, plus the outstanding
# CRM customer-detail view fix.
#
# Run from the repo root:   bash deployment/deploy-kingo-export.sh
#
# Everything here was verified locally first: both exports generate valid
# files, the page renders with the buttons, filters round-trip, employees are
# refused, and prod was confirmed to carry no drift on the files touched.
#
set -euo pipefail

cd "$(dirname "$0")/.."

# --- credentials -------------------------------------------------------------
set -a
. <(tr -d '\r' < deployment/.env.deploy | sed 's/^\xEF\xBB\xBF//')
set +a

PSCP="/c/Program Files/PuTTY/pscp.exe"
PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
R="$HRX_APP_ROOT"

run()  { "$PLINK" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }
push() { "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$1" "$SSH:$R/$2"; }

echo "==> 1/6  backing up the files being replaced"
run "cd $R && S=.bak-kbexport-\$(date +%Y%m%d-%H%M%S) \
  && cp app/Http/Controllers/tenant/KingoBingoController.php  app/Http/Controllers/tenant/KingoBingoController.php\$S \
  && cp resources/views/tenant/kingo-bingo/fill-targets.blade.php resources/views/tenant/kingo-bingo/fill-targets.blade.php\$S \
  && cp resources/views/tenant/crm-clients/show.blade.php     resources/views/tenant/crm-clients/show.blade.php\$S \
  && cp routes/tenant.php routes/tenant.php\$S \
  && echo \"    backups suffixed \$S\""

echo "==> 2/6  building the obfuscated controller (prod keeps this file wrapped)"
php_local="${HRX_LOCAL_PHP:-/c/laragon/bin/php/php-8.3.28-Win32-vs16-x64/php.exe}"
"$php_local" -r '
$src = file_get_contents("app/Http/Controllers/tenant/KingoBingoController.php");
$body = preg_replace("/^<\?php\s*/", "", $src, 1);
if ($body === $src) { fwrite(STDERR, "could not strip opening tag\n"); exit(1); }
$p = base64_encode(gzdeflate($body, 9));
$out = "<?php\n/* Protected by HRX Security */\neval(gzinflate(base64_decode(\x27".$p."\x27)));";
if (gzinflate(base64_decode($p)) !== $body) { fwrite(STDERR, "round-trip failed\n"); exit(1); }
file_put_contents(sys_get_temp_dir()."/kingo_obf.php", $out);
echo "    payload verified, ".strlen($out)." bytes\n";
'
OBF="$(cygpath -u "$(TMPDIR= "$php_local" -r 'echo sys_get_temp_dir();')" 2>/dev/null || echo "${TMP}")/kingo_obf.php"
[ -f "$OBF" ] || OBF="${TMP}/kingo_obf.php"

echo "==> 3/6  uploading files"
push "$OBF"                                                        "app/Http/Controllers/tenant/KingoBingoController.php"
push "app/Exports/KingoBingoTargetsExport.php"                     "app/Exports/KingoBingoTargetsExport.php"
push "resources/views/tenant/kingo-bingo/fill-targets.blade.php"   "resources/views/tenant/kingo-bingo/fill-targets.blade.php"
push "resources/views/tenant/kingo-bingo/fill-targets-pdf.blade.php" "resources/views/tenant/kingo-bingo/fill-targets-pdf.blade.php"
push "resources/views/tenant/crm-clients/show.blade.php"           "resources/views/tenant/crm-clients/show.blade.php"

echo "==> 4/6  patching routes in place (prod's copy differs; never uploaded wholesale)"
run "cd $R && /opt/alt/php84/usr/bin/php -r '
\$f = \"routes/tenant.php\";
\$s = file_get_contents(\$f);
if (strpos(\$s, \"kingo-bingo.fill-targets.excel\") !== false) { echo \"    routes already present\n\"; exit(0); }
\$anchor = \"/kingo-bingo/fill-targets\";
\$pos = strpos(\$s, \$anchor);
if (\$pos === false) { fwrite(STDERR, \"    anchor not found\n\"); exit(1); }
\$eol = strpos(\$s, \"\n\", \$pos);
\$bs = chr(92);
\$ctrl = \$bs.\"App\".\$bs.\"Http\".\$bs.\"Controllers\".\$bs.\"tenant\".\$bs.\"KingoBingoController::class\";
\$q = chr(39);
\$add = \"\n                Route::get({\$q}/kingo-bingo/fill-targets/export/excel{\$q}, [{\$ctrl}, {\$q}exportTargetsExcel{\$q}])->name({\$q}kingo-bingo.fill-targets.excel{\$q});\"
     . \"\n                Route::get({\$q}/kingo-bingo/fill-targets/export/pdf{\$q}, [{\$ctrl}, {\$q}exportTargetsPdf{\$q}])->name({\$q}kingo-bingo.fill-targets.pdf{\$q});\";
file_put_contents(\$f, substr(\$s, 0, \$eol) . \$add . substr(\$s, \$eol));
echo \"    2 routes inserted\n\";
'"

echo "==> 5/6  clearing caches"
run "cd $R && $HRX_PHP artisan view:clear && $HRX_PHP artisan route:clear && $HRX_PHP -l routes/tenant.php"

echo "==> 6/6  verifying on the server"
run "cd $R && $HRX_PHP -r '
require \"vendor/autoload.php\";
\$app = require \"bootstrap/app.php\";
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Mail::fake();
\$k = \$app->make(Illuminate\Contracts\Http\Kernel::class);
\$u = App\Models\User::role(\"admin\")->where(\"status\",\"active\")->first();

foreach ([\"/sales-visits/kingo-bingo/fill-targets\" => \"page\",
          \"/sales-visits/kingo-bingo/fill-targets/export/excel\" => \"excel\",
          \"/sales-visits/kingo-bingo/fill-targets/export/pdf\" => \"pdf\"] as \$url => \$label) {
    auth()->login(\$u);
    \$r = Illuminate\Http\Request::create(\$url, \"GET\");
    \$r->setLaravelSession(app(\"session.store\"));
    \$res = \$k->handle(\$r);
    ob_start(); \$res->sendContent(); \$b = ob_get_clean();
    printf(\"    %-6s status=%d  %.0f KB  %s\n\", \$label, \$res->getStatusCode(),
        strlen(\$b)/1024, \$res->headers->get(\"content-type\"));
    if (\$label === \"page\") {
        printf(\"    buttons on page: %s\n\", str_contains(\$b, \"exportGrid(\") ? \"present\" : \"** MISSING **\");
    }
}

auth()->login(App\Models\User::find(411));
\$r = Illuminate\Http\Request::create(\"/crm-clients/373\", \"GET\");
\$r->setLaravelSession(app(\"session.store\"));
printf(\"    crm-clients/373 status=%d\n\", \$k->handle(\$r)->getStatusCode());
'"

echo
echo "Done. If anything looks wrong, every replaced file has a .bak-kbexport-* copy beside it."
