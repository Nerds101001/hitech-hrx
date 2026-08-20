# HRX Telephony Integration — start here

**Hi Tech Group → CentroVision**

You asked for our API. This folder is it. Five files, in the order you should
open them.

---

| # | File | What it is |
|---|---|---|
| 1 | **`INTEGRATION-GUIDE.md`** | **Open this first.** The whole integration in five minutes, with working code in PHP, C#, Java, Python, Node and curl. |
| 2 | `HRX-Telephony.postman_collection.json` | 28 runnable requests. Import, set two variables, press Run. The signing is already written for you. |
| 3 | `hrms-telephony-webhook-v1.yaml` | The full OpenAPI spec — every field, every status code. Reference, once you are past the guide. |
| 4 | `centrovision-required-v1.yaml` | What we need from your side, in priority order. |
| 5 | `HRX-Telephony-UAT.xlsx` | The acceptance tests, plus two tabs we need you to fill in. |

---

## The whole thing in one paragraph

When a call happens on your gateway, POST a small JSON object to
`https://hrx.hitechgroup.in/api/telephony/v1/events`, signed with an HMAC. That
is the entire integration. You are not being asked to build reports, storage,
or a UI — HRX already has all of that and is waiting for the data.

## Two things to confirm before you start

**1. Every call must be reported — including manual handset dials.**

Not only calls placed through an API request. Inbound, IVR, predictive,
callback and forwarded calls too, each with `origin` set correctly.

This is the one requirement that decides whether the integration is worth
having. If half the floor dials manually and those calls are not reported, the
reports are wrong and nobody trusts them. It is test case UAT-05 and it is a
go/no-go item for us — so please confirm explicitly, either way, before
development starts rather than at acceptance.

**2. Which address will your webhooks come from?**

We allowlist `45.120.40.194`, the gateway we already reach for click-to-call.
If your webhook sender goes out from somewhere else, tell us and we will add
it — otherwise every request is refused and from your side it looks exactly
like a dead endpoint.

Note the host that *sends* webhooks is not always the host that *answers* your
agent API. Worth checking rather than assuming.

## Third thing — click-to-call is currently broken, and it is at your end

HRX has a Click to Call button that calls your agent API at
`45.120.40.194:18111`. **It stopped working.** We measured it on 20 August:

| From | Result |
|---|---|
| Hi Tech Group office network | connects in **33ms**, returns real data |
| **HRX production server (89.117.188.10)** | **blocked — times out** |

Your API is healthy; it is refusing our server specifically. It is blocked on
port 80 as well as 18111, while our server reaches other hosts on unusual high
ports without trouble — so this is a source-address allowlist on your side, not
a firewall on ours.

**Please add `89.117.188.10` to the allowlist for the agent API.** Until then
every click-to-call attempt hangs for ten seconds and reports that the dialer
is unreachable.

## Credentials

Two values, arriving in a **separate message** from this pack:

- **API key** — sent on every request as `Authorization: Bearer <key>`
- **Shared secret** — used to compute the signature, never sent over the wire

They are split deliberately: the key alone cannot post data, so a single
intercepted email is not enough to write into an HR system. Please store the
secret somewhere it will not end up in a git repository or a shared inbox.

## Testing

There is no separate UAT host — integrate against the live endpoint. That is
safe: until we configure your key it refuses everything, and once configured
your test events land in a call log that starts empty. Nothing you send
affects any other part of the HR system.

Start with request 1.1 in the Postman collection, `GET /health`. A `200` there
proves your key, your secret, your clock and your source address all at once.
Every other problem is easier to find once that passes.

## What we would like back

1. Confirmation on the two points above.
2. The **Vendor Questionnaire** tab of the UAT workbook — 15 numbered
   questions, numbered answers please.
3. The **Field Confirmation** tab — field by field, what you can and cannot
   supply. Anything you cannot supply is a five-minute conversation now and a
   change request later.

We believe this is a serialisation exercise rather than new telephony
development: every field we are asking for already appears on your own CDR,
Recording and User Session screens. If you see it differently on any specific
field, tell us which one.

## Contact

Nagender Kumar — Hi Tech Group
