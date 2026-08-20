# HRX × CentroVision — telephony integration pack

CentroVision cannot build reports for us, so HRX publishes an endpoint and they
push into it. This folder is what you hand them.

| File | Direction | What it is |
|---|---|---|
| `hrms-telephony-webhook-v1.yaml` | Vendor → HRX | **The API we publish.** Every call event, agent session and recording notification they must POST. This is the file they asked for. |
| `centrovision-required-v1.yaml` | HRX → Vendor | The counterpart: what they must expose so HRX can dial, pull a signed recording URL, and reconcile the CDR nightly. |
| `HRX-Telephony.postman_collection.json` | Both | 28 runnable requests with a working HMAC signing script, a sample payload for every event, and five negative tests. Both sides prove their half works before writing production code. |
| `HRX-Telephony-UAT.xlsx` | Both | The evidence file: 20 UAT cases, the 15-question vendor questionnaire, field-by-field confirmation, the 7-day reconciliation log, and the acceptance sign-off. |

The OpenAPI files are generated from the running implementation, not from a
design document — every status code, error code and required field in them is
enforced by `VerifyTelephonySignature` and `TelephonyIngestController`.

The Postman collection has been run end to end against the real endpoint:
28 requests, 37 assertions, zero failures.

## The one line that matters

> HRX will expose `POST https://hrx.hitechgroup.in/api/telephony/v1/events`.
> Every call must be reported there — **including calls dialled manually on the
> handset**, not only calls originated through the API.

That last clause is the whole game. These integrations usually fail not because
the API is wrong but because the vendor only hooks their API-originated path
while half the floor dials manually. **UAT-05 exists solely to prove that is
closed, and it is a go/no-go item.**

## Before you send anything

### 1. Generate the credentials

```bash
php -r 'echo "KEY=".bin2hex(random_bytes(24))."\nSECRET=".bin2hex(random_bytes(32))."\n";'
```

### 2. Set them on the UAT host

```dotenv
# Issued BY HRX TO the vendor
TELEPHONY_INBOUND_API_KEY=
TELEPHONY_INBOUND_SECRETS=          # comma separated; new secret FIRST during a rotation
TELEPHONY_ALLOWED_IPS=              # their fixed egress IP or CIDR; empty disables the check
TELEPHONY_DEFAULT_TENANT_ID=default

# Issued BY the vendor TO HRX — leave blank until they provide them
TELEPHONY_VENDOR_BASE_URL=
TELEPHONY_VENDOR_API_KEY=
TELEPHONY_VENDOR_VERIFY_TLS=false   # on-prem box with a self-signed cert

TELEPHONY_QUEUE=telephony
```

`TELEPHONY_DEFAULT_TENANT_ID` must never be empty. Every telephony table has a
unique index beginning with `tenant_id`, and MySQL treats NULLs in a unique
index as distinct — a null tenant silently stops `(tenant_id, event_id)` from
deduplicating anything, so a replayed event would be written twice with no
error. The config defaults it to `default` for exactly this reason.

### 3. Send the key and secret separately

Not in the same email as the spec. The whole point of the HMAC layer is that a
leaked API key alone is not enough to forge call records into an HR system;
mailing both together throws that away.

## Draft note to CentroVision

> Subject: HRX telephony integration — API spec and test collection
>
> Hello,
>
> As discussed, HRX is publishing the ingest API rather than asking you to build
> reports for us. Four files are attached.
>
> **`hrms-telephony-webhook-v1.yaml`** is the API we are publishing — the one
> you asked for. Everything you need to POST to us is in it, with a worked
> example for every event type.
>
> **`HRX-Telephony.postman_collection.json`** is runnable. Set `base_url`,
> `api_key` and `shared_secret` in the collection Variables tab (the key and
> secret follow in a separate message) and press Run. The pre-request script
> does the HMAC signing — read it once and you have the whole integration. Start
> with request 1.1 `GET /health`; a 200 there proves your key, your secret, your
> clock and your IP allowlist all at once.
>
> **`centrovision-required-v1.yaml`** is the counterpart — what we need from
> your side. Please do not build new endpoints to match those names. If your
> existing API differs in path or field naming, tell us and we adapt on our
> side; what matters is that the capability and the data exist. The file is
> ordered by priority.
>
> **`HRX-Telephony-UAT.xlsx`** holds the acceptance tests. Two tabs need your
> written input before we can size the work: **Vendor Questionnaire** (15
> questions) and **Field Confirmation** (field by field, what you can and cannot
> supply).
>
> Two things worth flagging up front:
>
> 1. **Every call must be reported, including manual handset dials** — not only
>    calls originated through the API. Inbound, IVR, predictive, callback and
>    forwarded calls too, each with `origin` set correctly. This is UAT-05 and it
>    is a go/no-go item for us, so please confirm explicitly whether your
>    platform can raise webhooks on those paths.
> 2. **`call_uid` must equal the RID/UID shown on your CDR screen**, so that when
>    our numbers disagree we can both open the same record instead of trading
>    screenshots.
>
> We believe this is a serialisation exercise rather than new telephony
> development — every field we are asking for already appears in your own CDR,
> Recording and User Session screens. If you see it differently on any specific
> field, please say which in the Field Confirmation tab.
>
> Could you come back on the questionnaire within five working days so we can
> agree scope?
>
> Best regards,
> Nagender Kumar
> Hi Tech Group

## What is already built on the HRX side

### The ingest API

- `POST /api/telephony/v1/events`, `/events/batch`, `/recordings/ready`,
  `/agent-sessions`, `/cdr/import`, and `GET /health`
- `VerifyTelephonySignature` — bearer key, HMAC over the raw body, ±300s clock
  skew, 10-minute nonce cache, IP allowlist
- Ten tables including a raw webhook delivery log and an append-only event log
- `telephony:reconcile`, scheduled nightly at 02:00

### The screens — `/user/telephony`

| Screen | Who sees it | What it is for |
|---|---|---|
| **Calls** | Everyone, scoped | Every call, filtered by date, direction, origin, outcome, disposition, employee or number |
| **Call detail** | Everyone, scoped | The full timeline, legs, recordings, hold periods, line quality and raw events |
| **Productivity** | Everyone, scoped | Login, active, talk, hold, wrap, idle, break, occupancy |
| **Analytics** | Everyone, scoped | Daily volume, dispositions, outcomes, abandons, call flows |
| **Webhook Log** | Admin / HR | Every raw payload, searchable, with replay |
| **Agent Mapping** | Admin / HR | The employee ↔ dialer-agent table the whole feature hangs off |
| **Exceptions** | Admin / HR | Unassigned calls, grouped so one action fixes a whole group |
| **Reconciliation** | Admin / HR | The nightly audit, with a run-it-now control |

"Scoped" means own calls for an employee, the team (including delegated
identities) for a manager, and everything for admin and HR — decided in one
place, `TelephonyCall::visibleTo()`. Customer numbers are masked for anyone
outside `telephony.recording.unmasked_roles`.

The menu entry sits under **Sales & Operations → Telephony**.

### What still waits on CentroVision

`CentroVisionClient` and the outbound half of `telephony:reconcile` are coded
against a JSON API **CentroVision has not built yet**. The live box speaks
plain-text `ACTION=DIAL` at `45.120.40.194:18111` and answers HTTP 200 whether
the dial worked or not (see `app/Services/ClickToCallService.php`). So until
they respond to `centrovision-required-v1.yaml`:

- **Reconciliation** has nothing to pull — it needs their `GET /cdr`, priority 1.
- **Recording playback** has nothing to fetch — it needs their signed-URL
  endpoint, priority 2.
- **Click-to-call correlation** is one-way. HRX now sends a `ref_id` on every
  dial, but their API returns no `call_uid` to store against it, and does not
  echo the `ref_id` back. The moment they do either, the ingest side stores it
  with no further change.

Everything driven by the **push** side — every screen above — works as soon as
they start posting events, which is the thing they can do without building
anything new.

## Running the collection yourself
```bash
npx newman run docs/telephony/HRX-Telephony.postman_collection.json --env-var "base_url=http://127.0.0.1:8000/api/telephony/v1" --env-var "api_key=YOUR_KEY" --env-var "shared_secret=YOUR_SECRET"
```
