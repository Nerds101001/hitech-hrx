# WhatsApp message — sending the API pack

Vendor-neutral. Replace `[Name]`; the attached PDF names nobody.

WhatsApp uses *bold*, _italic_ and ```monospace```. Keep paragraphs short; long
blocks collapse behind "Read more" on a phone.

**Important:** if the PDF goes by WhatsApp, send the API key and secret by
**email or a phone call** instead. The whole point of splitting them is that one
compromised channel is not enough. Putting both in the same chat removes that.

---

## Message 1 — with the PDF attached

Hi [Name],

Sharing the HRX Telephony API documentation as discussed.

*Endpoint:*
```https://hrx.hitechgroup.in/api/telephony/v1/events```

The attached PDF is the full reference — authentication, all endpoints with
examples, the event list, error codes and the test checklist.

I am also sending a zip by email with the Postman collection (28 ready-to-run
requests, signing already written), the OpenAPI file and the UAT workbook. Your
developer will want those.

*Your API key and secret will come by email separately* — please do not start
until you have them.

Two things we need confirmed in writing before you begin development:

*1.* Every call must be reported, _including calls dialled manually on the
handset_ — not only calls placed through the API. Inbound, IVR, predictive,
callback and forwarded calls too. This is a go/no-go point for us, since a large
share of our calls are dialled manually. Section 3.3 of the PDF.

*2.* Which IP address will your webhooks come from? We need to allowlist it. It
may not be the same server as your agent API, so please check.

First step once you have the credentials: run ```GET /health```. A 200 confirms
your key, secret, clock and IP all at once.

Could you come back to us within 5 working days so we can agree scope and
timeline?

Thanks,
Nagender Kumar
Hi Tech Group

---

## Shorter version, if you prefer

Hi [Name],

Attaching the HRX Telephony API documentation.

*Endpoint:* ```https://hrx.hitechgroup.in/api/telephony/v1/events```

The PDF covers everything — auth, endpoints, examples, error codes, test
checklist. Postman collection and OpenAPI file follow by email, along with your
API key and secret.

Two things to confirm before you start:

*1.* All calls must be reported, _including manual handset dials_ — not just API
calls. This is go/no-go for us.
*2.* Which IP will your webhooks come from, so we can allowlist it?

Start with ```GET /health``` once you have the credentials.

Thanks,
Nagender Kumar

---

## Message 2 — the credentials, by EMAIL or phone

Subject: HRX Telephony API — credentials

> API key: <paste from Telephony → Connection>
> Shared secret: <paste from Telephony → Connection>
>
> Both go into the Postman collection's Variables tab, as api_key and
> shared_secret.
>
> The shared secret is never transmitted — it is used only to compute the
> request signature, which we verify at our end. Please keep it out of shared
> inboxes and code repositories.
>
> Please confirm once GET /health returns 200.
