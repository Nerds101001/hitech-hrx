# Click-to-call is blocked — evidence and who to send it to

**Measured 20 August 2026.** HRX cannot reach the CentroVision dialer from the
production server. Everything below is reproducible.

Send **message 1 to Hostinger first**. They are the only party who can prove or
disprove their own side, you have a support contract with them, and their
answer is what makes the CentroVision conversation short.

Send **message 2 to CentroVision in parallel** — it costs nothing and if it is
their appliance, both threads converge faster.

---

## The measurements

| Test | From HRX production (`89.117.188.10`) | From Hi Tech Group office |
|---|---|---|
| ping `45.120.40.194` | **100% packet loss** | replies normally |
| TCP `45.120.40.194:18111` | **no response** | **connects in 51ms**, returns data |
| TCP `45.120.40.194:80` | no response | — |
| TCP `45.120.40.194:443` | no response | — |
| TCP `45.120.40.194:22` | no response | — |
| ping `8.8.8.8` / `1.1.1.1` / `google.com` | **0% loss** | — |
| TCP `portquiz.net:18111` | **OPEN** | — |
| TCP `8.8.8.8:443`, `1.1.1.1:443` | OPEN | — |

What that establishes:

- Not the port — 18111 outbound works to other hosts
- Not ICMP being filtered — ping works to Google and Cloudflare
- Not the application — raw shell TCP fails with no PHP involved
- Not the destination being down — the office reaches it in 51ms

One host, every protocol, every port, from one source address only.

---

## Message 1 — to Hostinger support

> **Subject:** Outbound connectivity blocked to a single host from my hosting account
>
> Account: `u989061032` · Server: `in-mum-web945.main-hosting.eu` ·
> Outbound IP: `89.117.188.10`
>
> My application needs to reach `45.120.40.194` on port `18111`. From my
> hosting account that host is completely unreachable, on every protocol and
> port I have tried:
>
> - `ping 45.120.40.194` → 100% packet loss
> - TCP to ports 22, 80, 443 and 18111 → no response, connection times out
>
> This is specific to that one host. From the same server:
>
> - `ping 8.8.8.8`, `1.1.1.1` and `google.com` → 0% packet loss
> - TCP to `portquiz.net:18111` → connects fine, so port 18111 outbound is not
>   the issue
> - TCP to `8.8.8.8:443` and `1.1.1.1:443` → connect fine
>
> From my office network, `45.120.40.194:18111` connects in 51ms and responds
> correctly, so the destination is up and serving.
>
> Could you please:
>
> 1. Confirm whether outbound traffic to `45.120.40.194` is blocked at your
>    network or firewall for this account or for the shared IP range?
> 2. Run a traceroute from the server to `45.120.40.194` and share the output?
>    I do not have `traceroute` or `tracepath` available on the account, so I
>    cannot see which hop the packets stop at.
>
> If your side is clear, that is equally useful — I will take the traceroute to
> the destination's operator.
>
> Thank you.

---

## Message 2 — to CentroVision

> **Subject:** HRX server cannot reach the dialer at 45.120.40.194
>
> Hello,
>
> Our HRX click-to-call integration cannot reach your dialer. I understand no
> IP allowlist is in place at your end, so I want to share exactly what we
> measured rather than ask you to check the same setting again.
>
> From our production server (`89.117.188.10`) on 20 August, `45.120.40.194` is
> unreachable on **every** protocol and port we tried — ping returns 100%
> packet loss, and TCP to 22, 80, 443 and 18111 all time out with no response.
>
> That same server pings Google and Cloudflare with 0% loss and connects
> outbound on port 18111 to other hosts without trouble, so it is neither a
> port restriction nor a general connectivity fault on our side. From our
> office network your dialer answers in 51ms and returns valid data, so the
> service itself is healthy.
>
> One source address, one destination host, everything dropped.
>
> Because it is dropped before anything application-level sees it, this would
> not appear in your dialer's own allowlist or agent configuration. Could
> someone check for:
>
> - a firewall or security appliance in front of the dialer that blocks
>   **hosting or datacentre address ranges** by default — this is a common
>   default and is usually invisible from the application's admin panel
> - a **geographic or country rule**, since our server's egress may be
>   attributed to a different location than our office
> - a rule at your **upstream provider** rather than on the dialer itself
>
> If you can allow `89.117.188.10`, click-to-call starts working immediately —
> nothing needs to change on our side. The same address will be needed for the
> nightly CDR pull once that endpoint exists.
>
> Happy to run any test from our server that would help.
>
> Kind regards,
> Nagender Kumar
> Hi Tech Group

---

## Why Hostinger first

"All IPs are allowed" is almost certainly true of the **dialer application's**
own allowlist. But this traffic is dropped before anything application-level
sees it — no TCP handshake completes at all — so that setting was never
involved.

Asking CentroVision to "allowlist our IP" invites the answer you already got.
Hostinger, by contrast, can answer definitively and can run the traceroute that
identifies the hop. Whichever way they answer, it moves things forward:

- **They do block it** → they remove the block, done
- **They do not** → you have written confirmation to hand CentroVision, and the
  traceroute showing where the packets actually stop
