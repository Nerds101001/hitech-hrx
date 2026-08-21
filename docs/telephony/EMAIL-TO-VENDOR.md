# Email to CentroVision — ready to send

**To:** CentroVision technical contact (the people who run the dialer at
`45.120.40.194` and asked us to publish an API)

**Attach:** `HRX-Telephony-Integration-Pack.zip`

**Before sending:** generate the key and secret in HRX —
**Telephony → Connection → Generate**. Copy the shared secret immediately; it is
encrypted afterwards and cannot be shown again.

---

## Message 1 — the API (send with the zip attached)

**Subject:** HRX telephony API — ready for your integration

> Dear [Name],
>
> Thank you for resolving the connectivity issue at your end. Click-to-call is
> working again from our server, and your agent API is now responding in about
> a tenth of a second. Please keep `89.117.188.10` allowlisted — the nightly
> CDR reconciliation described below will use the same address.
>
> As you requested, we have built the API on our side for you to push call data
> into. It is live and tested. Everything you need is attached.
>
> **The endpoint**
>
> `POST https://hrx.hitechgroup.in/api/telephony/v1/events`
>
> **What is attached**
>
> | File | What it is |
> |---|---|
> | `START-HERE.md` | Read this first — the whole thing in one page |
> | `INTEGRATION-GUIDE.md` | The integration in five minutes, with working code in PHP, C#, Java, Python, Node and curl |
> | `HRX-Telephony.postman_collection.json` | 28 runnable requests — import, add credentials, press Run |
> | `hrms-telephony-webhook-v1.yaml` | Full OpenAPI specification |
> | `centrovision-required-v1.yaml` | What we need from your side, in priority order |
> | `HRX-Telephony-UAT.xlsx` | Acceptance tests, plus two tabs we need you to complete |
>
> **Getting started**
>
> Your API key and shared secret follow in a separate message. Put them into the
> Postman collection's Variables tab as `api_key` and `shared_secret`, then run
> request **1.1 `GET /health`**. A `200` there confirms your key, your secret,
> your server clock and your source address all in one call — please do not
> start pushing call data until it passes.
>
> The signing is already written in the collection's pre-request script, and the
> integration guide has the same logic in six languages. In short: every request
> carries an HMAC-SHA256 signature over the raw request body, so a leaked key
> alone cannot be used to write into our HR system.
>
> There is no separate test host. Integrating against the live endpoint is safe:
> your test events land in a call log that starts empty and touches nothing else
> in the system.
>
> **Two things we need confirmed in writing before development starts**
>
> **1. Every call must be reported — including calls dialled manually on the
> handset.** Not only calls placed through an API request. Inbound, IVR,
> predictive, callback and forwarded calls too, each with `origin` set
> correctly.
>
> This single point decides whether the integration is worth having. A large
> share of our calls are dialled manually; if those are not reported, every
> report is wrong and nobody will trust the system. It is test case UAT-05 in
> the attached workbook and it is a go/no-go item for us. Please confirm plainly
> whether your platform can raise webhooks on those paths — a "no" now is far
> cheaper than a "no" during acceptance testing.
>
> **2. Which IP address will your webhooks originate from?** We need to
> allowlist it at our end. Please note this is not necessarily the same host as
> your agent API, so it is worth checking rather than assuming.
>
> **What we need back**
>
> - The two confirmations above
> - The **Vendor Questionnaire** tab of the UAT workbook — 15 numbered questions
> - The **Field Confirmation** tab — field by field, what you can and cannot
>   supply. Anything you cannot supply is a short conversation now and a change
>   request later.
> - A `campaign`, `agent` and `client_id` for each additional user we want to
>   enable for click-to-call
>
> Our understanding is that this is a serialisation exercise rather than new
> telephony development — every field we are asking for already appears on your
> own CDR, Recording and User Session screens. If you see it differently on any
> specific field, please tell us which one in the Field Confirmation tab.
>
> Could you come back to us within five working days so we can agree scope and
> timeline?
>
> Kind regards,
>
> Nagender Kumar
> Hi Tech Group

---

## Message 2 — the credentials (send separately)

Send on a **different channel** — WhatsApp, SMS or a phone call. Not a reply on
the same email thread.

> Credentials for the HRX telephony API, as mentioned:
>
> **API key:** `<paste from Telephony → Connection>`
> **Shared secret:** `<paste from Telephony → Connection>`
>
> Both go into the Postman collection's Variables tab, as `api_key` and
> `shared_secret`.
>
> The shared secret is never transmitted — it is used only to compute the
> signature on each request, and we compute the same one at our end to verify.
> Please keep it out of any shared inbox or code repository.
>
> Please confirm once `GET /health` returns 200 and we will arrange a live test
> call.

**Why the two messages are separate:** the API key alone cannot write anything
— a request also needs a valid HMAC signature made with the secret. Putting both
in one email thread means a single intercepted mailbox is enough to forge call
records into an HR system. Sending them apart means it is not.

---

## After they start

**Telephony → Connection** shows how many pushes have arrived, when the last one
was accepted, and which event types they are sending — so you can see directly
whether `manual_dial` events are actually coming through, rather than taking
their word for it.

**Telephony → Webhook Log** shows refused requests grouped by reason and source
IP, each with the fix. The usual first-week problems — a drifted clock,
re-serialised JSON, an unlisted IP — diagnose themselves there.
