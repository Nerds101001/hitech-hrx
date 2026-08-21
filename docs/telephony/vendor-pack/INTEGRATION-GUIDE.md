# HRX Telephony — integration guide for telephony vendors

**Read this first. It is the whole integration in about five minutes.**

The OpenAPI file (`hrms-telephony-webhook-v1.yaml`) is the formal, complete
reference — every field, every status code. This page is the practical version:
what to call, how to sign it, and working code you can paste.

---

## 1. What you are being asked to do

HRX exposes one HTTPS endpoint. When a call happens on your gateway, POST a
small JSON object describing it. That is the entire integration.

You are **not** being asked to build reports, a UI, or anything that stores
data. Just send the events.

```
POST https://hrx.hitechgroup.in/api/telephony/v1/events
```

**Every call must be reported — including calls dialled manually on the
handset, not only calls placed through an API request.** Most integrations of
this kind fail on exactly that point. If your platform cannot raise an event
for a manual dial, please say so at kickoff rather than at acceptance testing.

---

## 2. What you need from Hi Tech Group

| Thing | Used for |
|---|---|
| **API key** | Sent as `Authorization: Bearer <key>` |
| **Shared secret** | Used to compute the signature. **Never sent over the wire.** |

These arrive in two separate messages, deliberately. The key alone is not
enough to post data, so a single leaked email cannot be used to forge call
records into an HR system.

Hi Tech Group also allowlists your source address (currently
**45.120.40.194**). If your webhook sender goes out from a different address,
tell them — otherwise every request is refused with `403 ip_not_allowed`, which
from your side looks exactly like a dead endpoint.

---

## 3. The four headers

| Header | Value |
|---|---|
| `Authorization` | `Bearer <api_key>` |
| `X-HRX-Timestamp` | Unix time in **seconds** (not milliseconds) |
| `X-HRX-Nonce` | Any value unique per request. A UUID is ideal. |
| `X-HRX-Signature` | `sha256=<hex>` — see below |

Plus `Content-Type: application/json`.

---

## 4. The signature — the one thing to get right

```
signable  = timestamp + "." + nonce + "." + raw_body
signature = "sha256=" + lowercase_hex( HMAC_SHA256(shared_secret, signable) )
```

Three rules. The first two cause almost every failed integration:

1. **Build the JSON string once. Sign that exact string. Send that exact
   string.** Do not serialise it, sign it, then serialise it again to send —
   key order or spacing will differ by one character and the signature will
   fail every time.
2. **For `GET /health` the body is an empty string**, so the signable value
   ends with a dot: `"1755691200.abc-123."`
3. **A fresh nonce on every HTTP request**, including retries of the same
   event. HRX remembers nonces for 10 minutes and refuses reuse.

Your clock must be within **5 minutes** of ours. If you get
`stale_timestamp`, run NTP — that is the whole fix.

---

## 5. Working code

HMAC-SHA256 is in the standard library of every language. No SDK, no
dependency, nothing to install.

> The PHP, Python, Node and curl examples below were **executed against the
> real endpoint** while writing this — each returned `200` for `/health` and
> `202` for `/events`. The C# and Java examples use the identical algorithm but
> were not run on our side.

### PHP

```php
function hrxPost(string $base, string $key, string $secret, string $path, ?array $payload): array
{
    // Build the body ONCE. This exact string is signed and sent.
    $body  = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
    $ts    = (string) time();
    $nonce = bin2hex(random_bytes(16));

    $signature = hash_hmac('sha256', $ts . '.' . $nonce . '.' . $body, $secret);

    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $payload === null ? 'GET' : 'POST',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
            'X-HRX-Timestamp: ' . $ts,
            'X-HRX-Nonce: ' . $nonce,
            'X-HRX-Signature: sha256=' . $signature,
        ],
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$status, $response];
}
```

### C#

```csharp
using System.Security.Cryptography;
using System.Text;

static async Task<HttpResponseMessage> HrxPost(string baseUrl, string key, string secret,
                                               string path, string body)
{
    // `body` is the exact JSON string you will send. Do not re-serialise it.
    var ts = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString();
    var nonce = Guid.NewGuid().ToString();

    using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
    var hash = hmac.ComputeHash(Encoding.UTF8.GetBytes($"{ts}.{nonce}.{body}"));
    var signature = Convert.ToHexString(hash).ToLowerInvariant();

    using var client = new HttpClient();
    var request = new HttpRequestMessage(
        string.IsNullOrEmpty(body) ? HttpMethod.Get : HttpMethod.Post, baseUrl + path);

    if (!string.IsNullOrEmpty(body))
        request.Content = new StringContent(body, Encoding.UTF8, "application/json");

    request.Headers.Add("Authorization", "Bearer " + key);
    request.Headers.Add("X-HRX-Timestamp", ts);
    request.Headers.Add("X-HRX-Nonce", nonce);
    request.Headers.Add("X-HRX-Signature", "sha256=" + signature);

    return await client.SendAsync(request);
}
```

### Java

```java
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.net.URI;
import java.net.http.*;
import java.nio.charset.StandardCharsets;
import java.util.UUID;

static HttpResponse<String> hrxPost(String baseUrl, String key, String secret,
                                    String path, String body) throws Exception {
    // `body` is the exact JSON string you will send. Do not re-serialise it.
    String ts = String.valueOf(System.currentTimeMillis() / 1000);
    String nonce = UUID.randomUUID().toString();

    Mac mac = Mac.getInstance("HmacSHA256");
    mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
    byte[] raw = mac.doFinal((ts + "." + nonce + "." + body).getBytes(StandardCharsets.UTF_8));

    StringBuilder hex = new StringBuilder();
    for (byte b : raw) hex.append(String.format("%02x", b));

    HttpRequest request = HttpRequest.newBuilder()
        .uri(URI.create(baseUrl + path))
        .header("Content-Type", "application/json")
        .header("Authorization", "Bearer " + key)
        .header("X-HRX-Timestamp", ts)
        .header("X-HRX-Nonce", nonce)
        .header("X-HRX-Signature", "sha256=" + hex)
        .method(body.isEmpty() ? "GET" : "POST", HttpRequest.BodyPublishers.ofString(body))
        .build();

    return HttpClient.newHttpClient().send(request, HttpResponse.BodyHandlers.ofString());
}
```

### Python

```python
import hashlib, hmac, json, time, uuid, urllib.request

def hrx_post(base, key, secret, path, payload=None):
    # Build the body ONCE. This exact string is signed and sent.
    body = '' if payload is None else json.dumps(payload, separators=(',', ':'))
    ts, nonce = str(int(time.time())), str(uuid.uuid4())

    signature = hmac.new(secret.encode(),
                         f'{ts}.{nonce}.{body}'.encode(),
                         hashlib.sha256).hexdigest()

    req = urllib.request.Request(
        base + path,
        data=body.encode() if payload is not None else None,
        method='GET' if payload is None else 'POST',
        headers={
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {key}',
            'X-HRX-Timestamp': ts,
            'X-HRX-Nonce': nonce,
            'X-HRX-Signature': 'sha256=' + signature,
        })
    with urllib.request.urlopen(req) as r:
        return r.status, r.read().decode()
```

### Node.js

```javascript
const crypto = require('crypto');

async function hrxPost(base, key, secret, path, payload) {
  // Build the body ONCE. This exact string is signed and sent.
  const body = payload === undefined ? '' : JSON.stringify(payload);
  const ts = Math.floor(Date.now() / 1000).toString();
  const nonce = crypto.randomUUID();

  const signature = crypto.createHmac('sha256', secret)
                          .update(`${ts}.${nonce}.${body}`)
                          .digest('hex');

  return fetch(base + path, {
    method: payload === undefined ? 'GET' : 'POST',
    body: payload === undefined ? undefined : body,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${key}`,
      'X-HRX-Timestamp': ts,
      'X-HRX-Nonce': nonce,
      'X-HRX-Signature': 'sha256=' + signature,
    },
  });
}
```

### curl / shell

```bash
BASE="https://hrx.hitechgroup.in/api/telephony/v1"
KEY="your-key"
SECRET="your-secret"

TS=$(date +%s)
NONCE=$(cat /proc/sys/kernel/random/uuid)
BODY=""   # empty for /health -- note the signable string ends with a dot
SIG=$(printf '%s.%s.%s' "$TS" "$NONCE" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | awk '{print $NF}')

curl -s "$BASE/health" \
  -H "Authorization: Bearer $KEY" \
  -H "X-HRX-Timestamp: $TS" \
  -H "X-HRX-Nonce: $NONCE" \
  -H "X-HRX-Signature: sha256=$SIG"
```

---

## 6. Start here: `GET /health`

Before sending any call data, get this returning `200`:

```json
{ "status": "ok", "server_time": "2026-08-20T11:04:22+05:30", "api_version": "v1" }
```

A `200` proves four things at once — your key, your secret, your clock, and
your source address. Every other problem is easier to find once this passes.

---

## 7. The smallest useful event

Only four fields are mandatory. Everything else is sent when you know it.

```json
{
  "event": "call.completed",
  "event_id": "9f1b2c3d-4e5f-4a6b-8c7d-0e1f2a3b4c5d",
  "occurred_at": "2026-08-19T15:01:44+05:30",
  "call_uid": "CV-20260819-000512"
}
```

- `event_id` — unique per event, forever. This is what makes retries safe.
- `call_uid` — **must be the same value shown as RID/UID on your CDR screen**,
  so when the two systems disagree we can both open the same record instead of
  trading screenshots.
- `occurred_at` — ISO-8601 **with an offset**. Never a naive local string.

A real `call.completed` should carry the full CDR: agent, numbers, timestamps,
durations, disposition, recording id. See the OpenAPI file, or the worked
example in the Postman collection.

---

## 8. What the responses mean

| Code | Meaning | What to do |
|---|---|---|
| `202` | Stored. | Nothing. Do not wait for processing. |
| `200` + `"duplicate"` | Already had it. | **Success.** This is the expected answer to a retry. |
| `401` | Auth failed — read `error.code` | Fix it. **Do not retry.** |
| `403` | Your IP is not allowlisted | Send Hi Tech Group your real egress address. |
| `413` | Body over 5 MB | Split it. |
| `422` | Missing or malformed field | Fix it. **Do not retry.** |
| `429` | Too fast | Back off. |
| `5xx` | Our problem | **Retry** — see below. |

Every error names itself:

```json
{ "error": { "code": "invalid_signature",
             "message": "HMAC signature did not match",
             "request_id": "..." } }
```

Quote `request_id` and Hi Tech Group can tell you exactly why a request was
refused, rather than guessing.

### The error codes you are most likely to see

| Code | Almost always means |
|---|---|
| `invalid_signature` | The JSON was re-serialised between signing and sending. |
| `stale_timestamp` | Your server clock has drifted. Run NTP. |
| `replayed_nonce` | You reused a nonce. It must be fresh per HTTP request. |
| `ip_not_allowed` | Your webhooks leave from an address nobody told HRX about. |
| `invalid_api_key` | Wrong key, or it was rotated. |

---

## 9. Retry policy

- On `5xx` or timeout: retry at 1s, 5s, 30s, 2m, 10m, 1h, then hourly up to 24
  hours. **Keep the same `event_id`** — re-delivery is safe and answers
  `duplicate`.
- On `4xx` other than `429`: do not retry. Log it and tell us.
- After a long outage, replay the backlog to `POST /events/batch` — up to 500
  events per request, and one bad row never rejects the batch.

---

## 10. Before you say you are done

- [ ] `GET /health` returns `200`
- [ ] A real answered call appears in HRX with matching `call_uid` and
      durations within 2 seconds of your CDR
- [ ] **A call dialled manually on the handset produces the same events**, with
      `origin: "manual_dial"` — this is the go/no-go item
- [ ] Inbound and IVR calls too
- [ ] Re-sending an event returns `200 duplicate` and does not duplicate
- [ ] A deliberately wrong signature returns `401`

The Postman collection (`HRX-Telephony.postman_collection.json`) runs all of
these. Import it, fill in three variables, press **Run** — the report is your
evidence for sign-off.

---

## Questions

Anything unclear, or any field your platform cannot supply: raise it now, in
the **Field Confirmation** tab of `HRX-Telephony-UAT.xlsx`. A field flagged at
kickoff is a five-minute conversation. The same field discovered during
acceptance testing is a change request.
