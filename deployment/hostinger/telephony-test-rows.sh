#!/usr/bin/env bash
#
# Test rows in the live telephony tables.
#
#   bash deployment/hostinger/telephony-test-rows.sh list
#   bash deployment/hostinger/telephony-test-rows.sh delete --confirm
#
# Integration testing against production is safe by design here, but it leaves
# fabricated calls in a log that feeds real reports. This removes only rows
# whose identifier carries one of the test prefixes below -- never a bare
# "delete everything", because the same tables will hold real calls.
#
# `list` writes nothing and is the default. `delete` refuses to run without
# --confirm.
set -euo pipefail
cd "$(dirname "$0")/../.."
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }

ACTION="${1:-list}"
CONFIRM="${2:-}"

# Every identifier this session's testing introduced. Deliberately explicit:
# a wildcard that grew later could take real data with it.
PREFIXES='["TEST-", "VERIFY-", "ATTACK-"]'

# Refused requests are logged with call_uid NULL on purpose -- an unauthenticated
# caller does not get to write an identifier into an indexed column -- so the
# prefix rule cannot reach them and they have to be named by id.
#
# Only ids raised from this machine are listed. The refusals from other source
# addresses stay: one of them came from the vendor's own gateway, which is a
# real record of their end presenting no valid key, and deleting it would throw
# away the answer to a question they are likely to ask.
DELIVERY_IDS="${TELEPHONY_TEST_DELIVERY_IDS:-93,94,100,101,102,104,105,107}"

PHP_LIST='
  $prefixes = '"$PREFIXES"';
  $tables = [
    "telephony_call_records"      => "call_uid",
    "telephony_recordings"        => "recording_uid",
    "telephony_call_events"       => "call_uid",
    "telephony_call_legs"         => "call_uid",
    "telephony_webhook_deliveries"=> "call_uid",
  ];
  $total = 0;
  foreach ($tables as $table => $col) {
    if (! \Illuminate\Support\Facades\Schema::hasTable($table)) { continue; }
    $q = \Illuminate\Support\Facades\DB::table($table)->where(function ($w) use ($prefixes, $col) {
      foreach ($prefixes as $p) { $w->orWhere($col, "like", $p . "%"); }
    });
    $n = $q->count();
    $all = \Illuminate\Support\Facades\DB::table($table)->count();
    $total += $n;
    echo str_pad($table, 32), str_pad($n . " test", 12), "of ", $all, " total", PHP_EOL;
    foreach ($q->limit(12)->get([$col]) as $r) { echo "     ", $r->$col, PHP_EOL; }
  }
  echo "TOTAL TEST ROWS: ", $total, PHP_EOL;
'

if [[ "$ACTION" == "list" ]]; then
  remote "cd $HRX_APP_ROOT && $HRX_PHP artisan tinker --execute='$PHP_LIST'" 2>/dev/null | grep -v '^$'
  exit 0
fi

if [[ "$ACTION" == "delete" ]]; then
  if [[ "$CONFIRM" != "--confirm" ]]; then
    echo "refusing to delete without --confirm"; exit 1
  fi

  echo "── before ──"
  remote "cd $HRX_APP_ROOT && $HRX_PHP artisan tinker --execute='$PHP_LIST'" 2>/dev/null | grep TOTAL

  remote "cd $HRX_APP_ROOT && $HRX_PHP artisan tinker --execute='
    \$prefixes = $PREFIXES;
    \$tables = [
      \"telephony_call_records\"      => \"call_uid\",
      \"telephony_recordings\"        => \"recording_uid\",
      \"telephony_call_events\"       => \"call_uid\",
      \"telephony_call_legs\"         => \"call_uid\",
      \"telephony_webhook_deliveries\"=> \"call_uid\",
    ];
    foreach (\$tables as \$table => \$col) {
      if (! \Illuminate\Support\Facades\Schema::hasTable(\$table)) { continue; }
      \$n = \Illuminate\Support\Facades\DB::table(\$table)->where(function (\$w) use (\$prefixes, \$col) {
        foreach (\$prefixes as \$p) { \$w->orWhere(\$col, \"like\", \$p . \"%\"); }
      })->delete();
      echo \"deleted \", \$n, \" from \", \$table, PHP_EOL;
    }

    \$ids = array_filter(array_map(\"intval\", explode(\",\", \"$DELIVERY_IDS\")));
    if (\$ids) {
      // Refusals only. A processed delivery is never removed by id here --
      // those are reached by the prefix rule above, where the identifier makes
      // it obvious what is being deleted.
      \$n = \Illuminate\Support\Facades\DB::table(\"telephony_webhook_deliveries\")
        ->whereIn(\"id\", \$ids)->where(\"status\", \"rejected\")->delete();
      echo \"deleted \", \$n, \" refused deliveries by id\", PHP_EOL;
    }
  '" 2>/dev/null | grep -v '^$'

  echo "── after ──"
  remote "cd $HRX_APP_ROOT && $HRX_PHP artisan tinker --execute='$PHP_LIST'" 2>/dev/null | grep TOTAL
  exit 0
fi

echo "unknown action: $ACTION"; exit 1
