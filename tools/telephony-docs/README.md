# Telephony API reference — PDF generator

Regenerates `docs/telephony/vendor-pack/HRX-Telephony-API-Reference.pdf`.

```bash
python tools/telephony-docs/build.py
```

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

**Character set:** the built-in Helvetica and Courier fonts use WinAnsiEncoding,
which has no arrows, tick marks or box-drawing characters — those render as
solid black boxes. Keep the copy to ASCII plus ordinary punctuation. `build.py`
has no guard for this; the check is in the commit that added it, run with pypdf
against the output.
