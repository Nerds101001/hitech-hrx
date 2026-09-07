#!/usr/bin/env bash
#
# Did the pushed audio link actually land?
#
#   bash deployment/hostinger/telephony-check-recordings.sh [uid-prefix]
#
# A 202 from /recordings/ready means "stored and queued", not "normalised".
# The bug this feature fixes was a silent one -- a 202 followed by the URL being
# dropped on the floor -- so the only verification worth anything reads the row
# back out of the database.
set -euo pipefail
cd "$(dirname "$0")/../.."
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }

PREFIX="${1:-VERIFY-}"

remote "cd $HRX_APP_ROOT && $HRX_PHP artisan tinker --execute='
  \$rows = \App\Models\Telephony\TelephonyRecording::where(\"recording_uid\", \"like\", \"$PREFIX%\")
    ->orderBy(\"id\")->get();

  if (\$rows->isEmpty()) { echo \"no rows matching $PREFIX\", PHP_EOL; }

  foreach (\$rows as \$r) {
    echo \$r->recording_uid, PHP_EOL;
    echo \"   call_uid : \", \$r->call_uid, PHP_EOL;
    echo \"   url      : \", (\$r->recording_url ?: \"(none)\"), PHP_EOL;
    echo \"   expires  : \", (\$r->url_expires_at ?: \"(none)\"), PHP_EOL;
    echo \"   duration : \", (\$r->duration_seconds ?? \"(none)\"), \"  format: \", (\$r->format ?: \"-\"), PHP_EOL;
    echo \"   usable   : \", (\$r->hasUsableUrl() ? \"yes\" : \"no\"), PHP_EOL;
  }

  foreach (\App\Models\Telephony\TelephonyCall::where(\"call_uid\", \"like\", \"$PREFIX%\")->get() as \$c) {
    echo \"call \", \$c->call_uid, \" has_recording=\", (\$c->has_recording ? \"yes\" : \"no\"),
         \" talk=\", \$c->talk_seconds, \" complete=\", (\$c->is_complete ? \"yes\" : \"no\"), PHP_EOL;
  }
'" 2>/dev/null | grep -v '^$'
