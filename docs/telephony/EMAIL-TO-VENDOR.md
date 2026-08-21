# Ready-to-send messages

**Who this goes to:** CentroVision — your telephony vendor, the people who run
the dialer / GSM gateway at `45.120.40.194` and who asked you to publish an API
they can push call data into. Send it to their technical contact, the same
people who fixed the allowlist on 20 August.

*(Note the gateway IP itself belongs to Vayutel Technology Services, their
hosting/network provider. You deal with CentroVision; they deal with Vayutel.)*

Two messages, deliberately separate. Copy, paste, send.

---

## Before you send — three steps

**1. Generate the credentials** in HRX:
**Telephony → Connection → Generate**. Copy the shared secret immediately; it
is encrypted afterwards and cannot be shown again.

**2. Leave the allowlist field blank** on that form for now. `TELEPHONY_ALLOWED_IPS`
restricts who may push *into* HRX, and we do not yet know which address their
webhook sender uses — it is not necessarily the gateway address. If the guess
is wrong they are refused before they can prove their credentials work, and it
looks to them like a dead endpoint. Set it once you see their real IP on the
Webhook Log.

**3. Attach `HRX-Telephony-Integration-Pack.zip`** to message 1.

---

## Message 1 — the pack

**Subject:** HRX telephony integration — our API, spec and test collection

> Dear [name],
>
> Thank you for sorting the allowlist — click-to-call is working again at our
> end, and your API is now answering our server in about a tenth of a second.
> Please keep `89.117.188.10` allowlisted, as the nightly CDR reconciliation
> described in the attached files will use the same address.
>
> Now for the main piece. As discussed, rather than asking you to build reports
> for us, HRX now publishes an endpoint for you to push call data into.
> Everything you need is attached.
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
> confirm plainly whether your platform can raise webhooks on those paths — a
> "no" now is far cheaper than a "no" during acceptance testing.
>
> **2. Which IP address will your webhooks originate from?** We need to
> allowlist it at our end. Note this is not necessarily the same host as your
> agent API, so it is worth checking rather than assuming.
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
> Separately: we would like to enable click-to-call for more of our agents.
> Could you send a `campaign`, `agent` and `client_id` for each of the users we
> nominate? Nothing is needed on the network side now that the allowlist is in
> place.
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

> Credentials for the HRX telephony endpoint, as mentioned:
>
> API key: `<paste from the Connection screen>`
> Shared secret: `<paste from the Connection screen>`
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

## After they start

Watch **Telephony → Connection** and **Telephony → Webhook Log**.

The Connection screen shows how many pushes have arrived, when the last
accepted one was, and which event types they are sending — so you can see at a
glance whether `manual_dial` events are actually coming through, which is the
UAT-05 question.

The Webhook Log shows refused requests grouped by reason and source IP, each
with the fix spelled out. The usual first-week problems — a drifted clock,
re-serialised JSON, an unlisted IP — diagnose themselves there rather than
becoming a week of "we are sending" / "we see nothing".
