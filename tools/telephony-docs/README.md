# Telephony API reference — PDF generator and packer

Regenerates `docs/telephony/vendor-pack/HRX-Telephony-API-Reference.pdf`, then
repacks `docs/telephony/HRX-Telephony-Integration-Pack.zip` — the file that
actually reaches a vendor.

```bash
python tools/telephony-docs/build.py
python tools/telephony-docs/pack.py
```

The document is vendor-neutral by default, because the same pack goes to every
telephony vendor being onboarded and one addressed to a particular vendor reads
as a copy-paste when it lands with another. What differs per vendor is only the
credential. To address a covering copy to one of them:

```bash
python tools/telephony-docs/build.py "Acme Telecom"
```

That stamps the cover and footer only; the body stays in the second person.

Kept in the repository rather than produced by hand so the PDF can be reissued
when the API changes, instead of drifting out of date the moment someone edits
an endpoint. reportlab only — no external binary, nothing to install beyond
what the project already has.

Split across three files purely for size:

| File | Contains |
|---|---|
| `api_pdf_base.py` | Page templates, styles, table and callout helpers, cover, contents |
| `api_pdf_endpoints.py` | Section 3 — every endpoint with request and response examples |
| `api_pdf_reference.py` | Sections 4–10 — event catalogue, data dictionary, errors, reconciliation, acceptance checklist |

`build.py` stitches them together and runs `multiBuild` so the table of contents
resolves its page numbers.

`pack.py` builds the zip and refuses to write it if any file carries a vendor
name, an API key, something introduced as a credential, or internal deployment
detail. It checks the bytes about to be zipped rather than the source tree, so
an edit made after the last sweep cannot slip through — and it reaches inside
the PDF and the xlsx, where a plain `grep` over the compressed file finds
nothing. Documented example values are allow-listed by exact value in
`EXAMPLES`.

**Character set:** the built-in Helvetica and Courier fonts use WinAnsiEncoding,
which has no arrows, tick marks or box-drawing characters — those render as
solid black boxes. Keep the copy to ASCII plus ordinary punctuation. `build.py`
has no guard for this; the check is in the commit that added it, run with pypdf
against the output.
