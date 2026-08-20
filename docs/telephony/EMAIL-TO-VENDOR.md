# Ready-to-send messages

Two messages, deliberately separate. Copy, paste, send.

---

## Message 1 — the pack

**Attach:** `HRX-Telephony-Integration-Pack.zip`

**Subject:** HRX telephony integration — our API, spec and test collection

> Dear [name],
>
> As discussed, rather than asking you to build reports for us, HRX now
> publishes an endpoint for you to push call data into. Everything you need is
> attached.
>
> Open **START-HERE.md** first — it lists the five files in the order to read
> them. Your developer will want **INTEGRATION-GUIDE.md**, which covers the
> whole integration in about five minutes and includes working code for the
> request signing in PHP, C#, Java, Python, Node and curl. The full OpenAPI
> spec is in the pack as well.
>
> **HRX-Telephony.postman_collection.json** is runnable. Import it, paste in
> the two credentials that follow in my next message, and press Run — the
> signing script is already written. Please start with request 1.1,
> `GET /health`. A 200 there confirms your key, your secret, your server clock
> and your source address all in one call, and every later problem is easier to
> diagnose once it passes.
>
> The endpoint is live now:
> `POST https://hrx.hitechgroup.in/api/telephony/v1/events`
>
> There is no separate test host. Integrating against the live one is safe —
> until we enable your key it refuses everything, and once enabled your test
> events land in a call log that starts empty and touches nothing else in the
> HR system.
>
> **Two things I would like confirmed in writing before your development
> starts:**
>
> **1. Every call must be reported, including calls dialled manually on the
> handset** — not only calls placed through an API request. Inbound, IVR,
> predictive, callback and forwarded calls too, each with `origin` set
> correctly.
>
> This is the requirement that decides whether the whole integration is worth
> having. A large share of our calls are dialled manually; if those are not
> reported, every report is wrong and nobody will trust the system. It is test
> case UAT-05 in the attached workbook and it is a go/no-go item for us. Please
> confirm plainly whether your platform can raise webhooks on those paths —
> a "no" now is far cheaper than a "no" during acceptance testing.
>
> **2. Which IP address will your webhooks originate from?** We currently
> allowlist 45.120.40.194, the gateway we already reach for click-to-call. If
> your webhook sender goes out from a different address, tell me and I will add
> it. Note the host that sends webhooks is not always the host that answers
> your agent API.
>
> Two tabs of **HRX-Telephony-UAT.xlsx** need your input before we can agree
> scope: the **Vendor Questionnaire** (15 numbered questions) and **Field
> Confirmation** (field by field, what you can and cannot supply). Anything you
> cannot supply is a short conversation now and a change request later.
>
> Our reading is that this is a serialisation exercise rather than new
> telephony development — every field we are asking for already appears on your
> own CDR, Recording and User Session screens. If you see it differently on any
> specific field, please say which one in that tab.
>
> Could you come back to me within five working days so we can agree the scope
> and timeline?
>
> Kind regards,
> Nagender Kumar
> Hi Tech Group

---

## Message 2 — the credentials

**Send separately.** Different channel if you can — WhatsApp, SMS, or a phone
call. Not a reply on the same email thread.

Generate them first:

```bash
php -r 'echo "API KEY: ".bin2hex(random_bytes(24))."\nSECRET : ".bin2hex(random_bytes(32))."\n";'
```

> Credentials for the HRX telephony endpoint, as mentioned:
>
> API key: `<paste>`
> Shared secret: `<paste>`
>
> Both go in the collection's Variables tab — `api_key` and `shared_secret`.
>
> The secret is never transmitted; it is only used to compute the signature on
> each request, and we compute the same one at our end to verify. Please keep it
> out of any shared inbox or code repository.
>
> Let me know once `GET /health` returns 200 and we will move on to a live test
> call.

**Why separate:** the API key alone cannot write anything — a request also
needs a valid HMAC signature made with the secret. Sending both in one thread
means one intercepted mailbox is enough to forge call records into an HR
system. Sending them apart means it is not.

---

## Before you send

- [ ] Generate the key and secret with the command above
- [ ] Set them on production (see below) — **the vendor gets 401 until you do**
- [ ] Send message 1 with the zip attached
- [ ] Send message 2 on a different channel

Set on the production server, in `.env`:

```dotenv
TELEPHONY_INBOUND_API_KEY=<the key>
TELEPHONY_INBOUND_SECRETS=<the secret>
TELEPHONY_ALLOWED_IPS=
TELEPHONY_DEFAULT_TENANT_ID=default
```

Then `php artisan config:clear`.

Leave `TELEPHONY_ALLOWED_IPS` **empty** for their first handshake. If our guess
at their egress address is wrong, an allowlist blocks them before they can even
prove the credentials work, and it looks to them like a dead endpoint. Once you
see their real address in **Telephony → Webhook Log**, set it and clear the
config cache again.

## After they start

Watch **Telephony → Webhook Log**. It shows refused requests grouped by reason
and source IP with the fix for each, so the usual first-week problems — wrong
clock, re-serialised JSON, unlisted IP — diagnose themselves rather than
becoming a week of "we are sending" / "we see nothing".
