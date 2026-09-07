#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# HRX Telephony - push a "recording is ready" event
#
#   POST https://hrx.hitechgroup.in/api/telephony/v1/recordings/ready
#
# Every request is signed. The signature covers the timestamp, a nonce and the
# exact body bytes, so it cannot be a fixed curl line - hence this wrapper.
# Requires only bash, curl and openssl.
#
#   export HRX_API_KEY="hrx_..."
#   export HRX_SHARED_SECRET="..."
#   bash curl-recording-ready.sh
# ---------------------------------------------------------------------------
set -euo pipefail

# Credentials arrive from Hi Tech Group on a separate channel from this file.
# Keep them in the environment rather than editing them into the script, so a
# copy of the script is never a copy of the secret.
API_KEY="${HRX_API_KEY:?set HRX_API_KEY first}"
SHARED_SECRET="${HRX_SHARED_SECRET:?set HRX_SHARED_SECRET first}"

BASE_URL="https://hrx.hitechgroup.in/api/telephony/v1"

# --- The event. Replace the two UIDs with the real ones for the call. -------
CALL_UID="${1:-CV-20260907-000512}"
RECORDING_UID="${2:-REC-20260907-000512}"

# uuidgen is missing on plenty of minimal images and on Git Bash, so fall back
# to the kernel's UUID source and then to openssl. event_id must be a real
# UUID -- HRX validates the format and uses it to discard duplicates.
uuid() {
  if command -v uuidgen >/dev/null 2>&1; then
    uuidgen | tr 'A-Z' 'a-z'
  elif [ -r /proc/sys/kernel/random/uuid ]; then
    cat /proc/sys/kernel/random/uuid
  else
    openssl rand -hex 16 | sed 's/\(........\)\(....\)\(....\)\(....\)\(............\)/\1-\2-\3-\4-\5/'
  fi
}

EVENT_ID="$(uuid)"
OCCURRED_AT="$(date +%Y-%m-%dT%H:%M:%S%z | sed 's/\(..\)$/:\1/')"

# Build the body on ONE line and sign exactly these bytes. Do not pretty-print
# it or re-serialise it after signing - key order and whitespace are part of
# the signature, and changing either is the most common cause of a 401 here.
BODY="{\"event\":\"recording.ready\",\"event_id\":\"${EVENT_ID}\",\"occurred_at\":\"${OCCURRED_AT}\",\"call_uid\":\"${CALL_UID}\",\"recording_uid\":\"${RECORDING_UID}\",\"recording_type\":\"call\",\"duration_seconds\":198,\"format\":\"wav\",\"channels\":\"stereo\",\"size_bytes\":3168000}"

TIMESTAMP="$(date +%s)"
NONCE="$(uuid)"

# signable = timestamp + "." + nonce + "." + raw body
SIGNATURE=$(printf '%s.%s.%s' "$TIMESTAMP" "$NONCE" "$BODY" \
  | openssl dgst -sha256 -hmac "$SHARED_SECRET" -r | cut -d' ' -f1)

curl -sS -X POST "${BASE_URL}/recordings/ready" \
  -H "Authorization: Bearer ${API_KEY}" \
  -H "X-HRX-Timestamp: ${TIMESTAMP}" \
  -H "X-HRX-Nonce: ${NONCE}" \
  -H "X-HRX-Signature: sha256=${SIGNATURE}" \
  -H "Idempotency-Key: ${EVENT_ID}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --data-raw "${BODY}" \
  -w '\nHTTP %{http_code}\n'

# Expected: HTTP 202 {"status":"accepted",...}
#           HTTP 200 {"status":"duplicate",...}  - same event_id twice, safe
#           HTTP 401 - check the clock, the key, or that you signed the raw body
#           HTTP 422 - a required field is missing or malformed
