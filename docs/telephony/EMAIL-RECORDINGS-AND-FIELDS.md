# Email to the vendor technical team — API, credentials and field reference

**Attach:** `curl-recording-ready.sh` and `HRX-Telephony-Integration-Pack.zip`

**Before sending:** regenerate the credential at **Telephony → Connection** and
replace the two values marked `<<< PASTE >>>` below. The pair currently issued
appears in this repository's git history, so it should not be the pair a vendor
is asked to use.

---

**Subject:** HRX Telephony API — endpoint, credentials and field reference

> Dear Team,
>
> Everything needed to push call data into HRX is below. The endpoint is live
> and has been tested end to end.
>
> ---
>
> ## 1. Endpoint
>
> ```
> Base URL   https://hrx.hitechgroup.in/api/telephony/v1
> ```
>
> | Method and path | Purpose |
> |---|---|
> | `GET /health` | Verify credentials, clock and source IP. **Start here.** |
> | `POST /events` | One call event, as it happens |
> | `POST /events/batch` | Drain a backlog after an outage (max 500) |
> | `POST /recordings/ready` | Tell us a recording is available |
> | `POST /agent-sessions` | Agent login, state, break, logout |
> | `POST /cdr/import` | Bulk CDR replay |
>
> ---
>
> ## 2. Credentials
>
> ```
> API key        <<< PASTE API KEY >>>
> Shared secret  <<< PASTE SHARED SECRET >>>
> ```
>
> The shared secret is **never transmitted**. It is used only to compute the
> request signature, which we verify at our end. Please keep it out of shared
> inboxes, ticket systems and code repositories.
>
> ---
>
> ## 3. Authentication
>
> Four headers on every request:
>
> | Header | What it is |
> |---|---|
> | `Authorization: Bearer <api key>` | Identifies you |
> | `X-HRX-Timestamp` | Unix epoch seconds. Must be within **300s** of our clock |
> | `X-HRX-Nonce` | A fresh UUID per request. Remembered for 10 minutes |
> | `X-HRX-Signature` | `sha256=` + HMAC-SHA256 as below |
>
> ```
> signable  = timestamp + "." + nonce + "." + raw_request_body
> signature = "sha256=" + hex(HMAC_SHA256(shared_secret, signable))
> ```
>
> **Sign the exact bytes you are about to send.** Do not parse the JSON and
> re-serialise it before signing — key order and whitespace differ and every
> signature then fails. This is the most common cause of a 401 here.
>
> The API key alone cannot write anything without a valid signature, which is
> why the two values are handled separately.
>
> Optionally, tell us the source IP your webhooks originate from and we will
> allowlist it. It is often not the same host as your agent API.
>
> ---
>
> ## 4. Working example — recording ready
>
> The attached `curl-recording-ready.sh` is runnable as-is. Set the two
> environment variables and run it; it signs and posts a real request.
>
> ```bash
> export HRX_API_KEY="..."
> export HRX_SHARED_SECRET="..."
> bash curl-recording-ready.sh
> ```
>
> It sends:
>
> ```
> POST https://hrx.hitechgroup.in/api/telephony/v1/recordings/ready
> Authorization: Bearer <api key>
> X-HRX-Timestamp: 1757237168
> X-HRX-Nonce: 4f8c1a2e-6b3d-4e7a-9c15-2d8f0b6a1e93
> X-HRX-Signature: sha256=<hmac>
> Idempotency-Key: <event_id>
> Content-Type: application/json
>
> {"event":"recording.ready","event_id":"<uuid>","occurred_at":"2026-09-07T15:36:08+05:30","call_uid":"CV-20260907-000512","recording_uid":"REC-20260907-000512","recording_type":"call","duration_seconds":198,"format":"wav","channels":"stereo","size_bytes":3168000}
> ```
>
> Response `202 {"status":"accepted", ...}`.
>
> **Please do not send a playable recording URL.** Send `recording_uid` only.
> When a permitted HRX user presses play, HRX asks your
> `GET /recordings/{uid}/signed-url` endpoint for a short-lived link and streams
> the audio itself, so recordings never leave your gateway unauthenticated and
> every playback is permission-checked and logged. A URL sent to us today is
> discarded — there is no field for it.
>
> ---
>
> ## 5. Field reference — names exactly as stored in HRX
>
> These are the column names in our database, so a field named here is a field
> that lands. Anything not listed is ignored.
>
> ### Call — `telephony_call_records`
>
> | JSON in your payload | HRX column |
> |---|---|
> | `call_uid` | `call_uid` — **primary key for the whole call. Must equal the RID/UID on your CDR screen.** |
> | `parent_call_uid` | `parent_call_uid` |
> | `agent.agent_id` | `agent_id` |
> | `agent.agent_code` | `agent_code` |
> | `agent.agent_name` | `agent_name` |
> | `agent.extension` | `extension` |
> | `agent.agent_number` | `agent_number` |
> | `origin` | `origin` — `api_click_to_call` \| `manual_dial` \| `inbound` \| `ivr` \| `predictive` \| `callback` \| `forwarded` |
> | `direction` | `direction` — `inbound` \| `outbound` \| `internal` |
> | `mode` | `mode` — `auto` \| `manual` |
> | `caller_number` | `caller_number` |
> | `callee_number` | `callee_number` |
> | `did_number` | `did_number` |
> | `campaign_id` / `campaign_name` | `campaign_id` / `campaign_name` |
> | `queue`, `skill` | `queue`, `skill` |
> | `lead_source`, `lead_id`, `customer_name` | same names |
> | `gateway_id`, `channel`, `sim_msisdn`, `operator` | same names |
> | `status` | `status` |
> | `hangup_by`, `hangup_cause`, `hangup_cause_code` | same names |
> | `was_transferred`, `was_conferenced`, `was_forwarded` | same names |
> | `hold_events`, `quality`, `cost` | same names (JSON) |
>
> **`timestamps` object** — each key lands in the column of the same name:
> `initiated_at`, `agent_ring_at`, `agent_answer_at`, `customer_ring_at`,
> `answered_at`, `ended_at`, `wrapup_start_at`, `wrapup_end_at`,
> `queue_enter_at`, `queue_exit_at`. ISO 8601 with offset.
>
> **`durations` object** — integer seconds, same-named columns:
> `agent_ring_seconds`, `customer_ring_seconds`, `pdd_seconds`,
> `queue_wait_seconds`, `talk_seconds`, `hold_seconds`, `wrap_seconds`,
> `total_seconds`, `billed_seconds`.
>
> **`disposition` object** — note these differ:
>
> | JSON | HRX column |
> |---|---|
> | `disposition.code` | `disposition_code` |
> | `disposition.name` | `disposition_name` |
> | `disposition.sub_code` | `sub_disposition_code` |
> | `disposition.sub_name` | `sub_disposition_name` |
> | `disposition.remarks` | `remarks` |
> | `disposition.callback_at` | `callback_at` |
>
> **`recording.available`** sets `has_recording`.
>
> ### Recording — `telephony_recordings`
>
> `recording_uid`, `call_uid`, `recording_type` (`call` \| `transfer_leg` \|
> `conference` \| `voicemail`), `duration_seconds`, `format`, `size_bytes`,
> `channels`, `checksum_sha256`, `retention_until`, `ready_at`
> (from `occurred_at`).
>
> Stereo is strongly preferred — agent and customer on separate channels.
>
> ### Agent session — `telephony_agent_sessions`
>
> `session_id`, `agent_id`, `agent_code`, `extension`, `agent_number`, `mode`,
> `campaign_id`, `campaign_name`, `login_at`, `logout_at`, `login_seconds`,
> `ready_seconds`, `talk_seconds`, `hold_seconds`, `wrap_seconds`,
> `idle_seconds`, `break_seconds`, `occupancy_percent`, `calls_handled`,
> `calls_missed`, `breaks`, `device`.
>
> Please send your own computed totals on `agent.logout`. We store them verbatim
> rather than deriving them, so your User Session report and ours cannot drift
> apart.
>
> ---
>
> ## 6. How we match your agent to our employee
>
> This is the one that decides whether a call reaches the right person, so it is
> worth getting right. We try, in order:
>
> 1. `agent.hrms_employee_id` — our own employee code, if you can carry it.
>    **Most reliable. Please use it if at all possible.**
> 2. `agent.agent_id`
> 3. `agent.agent_code`
> 4. `agent.extension`
> 5. `agent.agent_number`
>
> The first one that matches wins. A call we cannot match is stored and flagged
> unassigned rather than dropped, but it will not appear on that employee's
> report until the mapping is fixed.
>
> ---
>
> ## 7. Responses and error codes
>
> | Status | Meaning |
> |---|---|
> | `202 accepted` | Stored |
> | `200 duplicate` | Same `event_id` sent again. Safe — please do retry on failure |
> | `401 invalid_api_key` | Key missing or wrong |
> | `401 invalid_signature` | HMAC did not match — usually re-serialised JSON |
> | `401 stale_timestamp` | Clock more than 300s out. Please run NTP |
> | `401 replayed_nonce` | Nonce reused. Generate a fresh UUID per request |
> | `403 ip_not_allowed` | Source IP not allowlisted — tell us the IP |
> | `413 payload_too_large` | Over 5 MB. Split across several batch calls |
> | `422 validation_failed` | A required field is missing or malformed |
> | `429` | Over 600 requests per minute |
>
> Every rejection carries a `request_id`. Quote it and we can tell you exactly
> what was refused and why.
>
> Send `event_id` as a UUID and reuse it on retries — that is what makes a retry
> safe. `Idempotency-Key` set to the same value is recommended.
>
> ---
>
> ## 8. What we need from you
>
> 1. **Confirmation that every call is reported, including calls dialled
>    manually on the handset** — not only calls placed through an API request.
>    Inbound, IVR, predictive, callback and forwarded too, each with `origin`
>    set correctly. A large share of our calls are dialled manually, so this is
>    a go/no-go point for us.
> 2. **The source IP your webhooks originate from**, so we can allowlist it.
> 3. **Confirmation that you can expose `GET /recordings/{uid}/signed-url`** for
>    playback.
> 4. Any field in section 5 you **cannot** supply — please say so now rather
>    than at acceptance testing.
>
> First step at your end: run `GET /health`. A `200` confirms the key, the
> secret, your clock and your source IP in a single call. Please do not begin
> sending call data until it passes.
>
> We would appreciate a response within five working days so we can agree scope
> and timeline.
>
> Kind regards,
>
> Nagender Kumar
> Hi Tech Group
