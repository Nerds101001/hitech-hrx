# Email to CentroVision — ready to send

**Attach:** `HRX-Telephony-Integration-Pack.zip`

**Before sending:** generate the key and secret in HRX —
**Telephony → Connection → Generate**. Copy the shared secret immediately; it is
encrypted afterwards and cannot be shown again.

---

## Message 1 — the API

**Subject:** HRX telephony API — ready for your integration

> Dear [Name],
>
> As discussed, we have published an API on our side for you to send call data
> into. It is live and tested, and everything you need is attached.
>
> **Endpoint**
>
> `POST https://hrx.hitechgroup.in/api/telephony/v1/events`
>
> **Attached**
>
> | File | Purpose |
> |---|---|
> | `START-HERE.md` | Read first — the integration in one page |
> | `INTEGRATION-GUIDE.md` | For your developer — includes working code in PHP, C#, Java, Python, Node and curl |
> | `HRX-Telephony.postman_collection.json` | 28 runnable requests — import, add credentials, press Run |
> | `hrms-telephony-webhook-v1.yaml` | Full OpenAPI specification |
> | `centrovision-required-v1.yaml` | What we require from your side |
> | `HRX-Telephony-UAT.xlsx` | Acceptance tests and two tabs for your input |
>
> **Getting started**
>
> Your API key and shared secret follow in a separate message. Enter them in the
> Postman collection's Variables tab as `api_key` and `shared_secret`, then run
> request **1.1 `GET /health`**. A `200` confirms your key, secret, server clock
> and source address in a single call. Please do not begin sending call data
> until it passes.
>
> Authentication is an HMAC-SHA256 signature over the raw request body, sent
> alongside the API key. The signing logic is already written in the collection's
> pre-request script, and the integration guide shows the same in six languages.
>
> There is no separate test host. Sending to the live endpoint is safe — your
> test events land in a call log that starts empty and affects nothing else.
>
> **Two points we need confirmed in writing before development begins**
>
> **1. Every call must be reported, including calls dialled manually on the
> handset** — not only calls placed through an API request. Inbound, IVR,
> predictive, callback and forwarded calls as well, each with `origin` set
> correctly.
>
> A large share of our calls are dialled manually. If those are not reported the
> figures will be wrong, so this is a go/no-go point for us and is covered by
> test case UAT-05 in the attached workbook. Please confirm plainly whether your
> platform can raise webhooks on those paths.
>
> **2. Which IP address will your webhooks originate from?** We need to
> allowlist it at our end. It may not be the same host as your agent API, so
> please confirm rather than assume.
>
> **What we need back**
>
> - The two confirmations above
> - The **Vendor Questionnaire** tab — 15 numbered questions
> - The **Field Confirmation** tab — field by field, what you can and cannot
>   supply
> - A `campaign`, `agent` and `client_id` for each additional user we wish to
>   enable for click-to-call
>
> Every field we are asking for already appears on your own CDR, Recording and
> User Session screens, so we expect this to be a matter of sending existing data
> rather than new development. If you see it differently on any field, please
> note it in the Field Confirmation tab.
>
> We would appreciate your response within five working days so we can agree
> scope and timeline.
>
> Kind regards,
>
> Nagender Kumar
> Hi Tech Group

---

## Message 2 — the credentials

Send on a **different channel** — WhatsApp, SMS or a phone call. Not a reply on
the same email thread.

> Credentials for the HRX telephony API:
>
> **API key:** `<paste from Telephony → Connection>`
> **Shared secret:** `<paste from Telephony → Connection>`
>
> Both go into the Postman collection's Variables tab, as `api_key` and
> `shared_secret`.
>
> The shared secret is never transmitted — it is used only to compute the
> request signature, which we verify at our end. Please keep it out of shared
> inboxes and code repositories.
>
> Please confirm once `GET /health` returns 200.

**Why separate:** the API key alone cannot write anything without a valid
signature made with the secret. Keeping them in different places means one
intercepted mailbox is not enough to write into the HR system.

---

## After they start

**Telephony → Connection** shows how many pushes have arrived, when the last was
accepted, and which event types — so you can see whether `manual_dial` events
are genuinely coming through.

**Telephony → Webhook Log** shows refused requests grouped by reason and source
IP, each with the cause.
