#!/usr/bin/env bash
#
# Telephony API credentials on production.
#
#   bash deployment/hostinger/telephony-credentials.sh list
#   bash deployment/hostinger/telephony-credentials.sh rotate "<label>"
#
# `rotate` mints a NEW key and secret, carries the old credential's label and
# IP allowlist across, and DEACTIVATES the old row. It is a replacement rather
# than a secret rotation on the same key, because the pair being replaced leaked
# into this repository's git history -- so the key has to die with the secret,
# not just the secret.
#
# The secret is printed ONCE. It is encrypted at rest and there is no way to
# read it back afterwards; that is deliberate, and losing it means rotating
# again rather than recovering it.
#
# Nothing here is written to a file. Do not paste the output into the repo.
set -euo pipefail

cd "$(dirname "$0")/../.."
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"
PHP="$HRX_PHP"
remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }

ACTION="${1:-list}"

if [[ "$ACTION" == "list" ]]; then
  remote "cd $APP && $PHP artisan tinker --execute='
    foreach (\App\Models\Telephony\TelephonyCredential::orderBy(\"id\")->get() as \$c) {
      echo str_pad(\$c->id, 4), str_pad(\$c->label ?? \"(no label)\", 24),
           str_pad(\$c->is_active ? \"active\" : \"INACTIVE\", 10),
           str_pad(substr(\$c->api_key, 0, 12) . \"...\", 18),
           \"ips=\", json_encode(\$c->allowlist()),
           \" last_used=\", (\$c->last_used_at ?? \"never\"), PHP_EOL;
    }
  '" 2>/dev/null | grep -v '^$'
  exit 0
fi

if [[ "$ACTION" == "rotate" ]]; then
  LABEL="${2:?usage: rotate \"<label>\"}"
  echo "Minting a new credential labelled: $LABEL"
  echo "Every currently active credential will be deactivated."
  echo
  remote "cd $APP && $PHP artisan tinker --execute='
    \$old = \App\Models\Telephony\TelephonyCredential::where(\"is_active\", true)->get();

    // Carry the allowlist across. Losing it would quietly widen access at the
    // moment we are trying to narrow it.
    \$ips = [];
    foreach (\$old as \$c) { \$ips = array_merge(\$ips, \$c->allowlist() ?? []); }
    \$ips = array_values(array_unique(array_filter(\$ips)));

    \$key = \App\Models\Telephony\TelephonyCredential::newApiKey();
    \$secret = \App\Models\Telephony\TelephonyCredential::newSecret();

    \$new = \App\Models\Telephony\TelephonyCredential::create([
      \"tenant_id\" => config(\"telephony.inbound.default_tenant_id\"),
      \"label\" => \"$LABEL\",
      \"api_key\" => \$key,
      \"secret\" => \$secret,
      // allowed_ips is a comma-separated text column, not JSON -- allowlist()
      // explodes it on commas. Handing it an array would store the word
      // \"Array\" and quietly refuse every source address.
      \"allowed_ips\" => \$ips ? implode(\",\", \$ips) : null,
      \"is_active\" => true,
    ]);

    foreach (\$old as \$c) { \$c->is_active = false; \$c->save(); }

    echo PHP_EOL;
    echo \"NEW CREDENTIAL (id \", \$new->id, \")\", PHP_EOL;
    echo \"  label   : \", \$new->label, PHP_EOL;
    echo \"  api_key : \", \$key, PHP_EOL;
    echo \"  secret  : \", \$secret, PHP_EOL;
    echo \"  allowed : \", json_encode(\$ips ?: \"any\"), PHP_EOL;
    echo \"  retired : \", \$old->count(), \" older credential(s)\", PHP_EOL;
  '" 2>/dev/null
  exit 0
fi

if [[ "$ACTION" == "allowlist" ]]; then
  IPS="${2:?usage: allowlist \"1.2.3.4,5.6.7.8\"  (or \"\" to clear)}"
  echo "Setting the source allowlist on every active credential to: ${IPS:-<none>}"
  echo
  # Worth saying out loud: once set, ONLY these addresses can push. Requests
  # from anywhere else -- including from here -- are refused with 403, which is
  # the allowlist working rather than a fault.
  remote "cd $HRX_APP_ROOT && $HRX_PHP artisan tinker --execute='
    foreach (\App\Models\Telephony\TelephonyCredential::where(\"is_active\", true)->get() as \$c) {
      \$c->allowed_ips = trim(\"$IPS\") === \"\" ? null : trim(\"$IPS\");
      \$c->save();
      echo \$c->label, \" -> \", json_encode(\$c->allowlist() ?? \"any\"), PHP_EOL;
    }
  '" 2>/dev/null | grep -v '^$'

  # resolveCredential() caches the row for 60s, so a change is not instant.
  echo
  echo "Takes effect within 60 seconds (the credential lookup is cached)."
  exit 0
fi

echo "unknown action: $ACTION"
exit 1
