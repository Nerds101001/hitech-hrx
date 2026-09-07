# -*- coding: utf-8 -*-
"""One-page quick start for a telephony vendor.

The 22-page reference is right for a developer mid-integration and wrong for
the first email: nobody reads it before deciding whether this is a week of work
or an afternoon. This is the one page that gets them to a 200 on /health, with
the full reference left as the attachment they open next.

Deliberately fits a single A4 side -- the build asserts it, because a one-pager
that quietly becomes two has lost the only property that made it useful.

Character set: the built-in Helvetica and Courier faces use WinAnsiEncoding, so
anything outside latin-1 renders as a black box. ASCII plus ordinary
punctuation only.
"""
import os

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    BaseDocTemplate, Frame, PageTemplate, Paragraph, Preformatted, Spacer,
    Table, TableStyle,
)

OUT = 'docs/telephony/vendor-pack/HRX-Telephony-QuickStart.pdf'

# Same palette as the full reference, so the two read as one set of documents.
TEAL = colors.HexColor('#00706E')
INK = colors.HexColor('#1F2933')
MUTED = colors.HexColor('#5B6B7B')
RULE = colors.HexColor('#C7D2DC')
CODE_BG = colors.HexColor('#F4F7F9')
BAND = colors.HexColor('#EDF3F6')
WARN = colors.HexColor('#B32020')

BODY = ParagraphStyle('body', fontName='Helvetica', fontSize=8.1, leading=10.3,
                      textColor=INK, spaceAfter=0)
H2 = ParagraphStyle('h2', fontName='Helvetica-Bold', fontSize=9.2, leading=11,
                    textColor=TEAL, spaceBefore=3.4, spaceAfter=2.2)
CELL = ParagraphStyle('cell', fontName='Helvetica', fontSize=7.8, leading=9.4,
                      textColor=INK)
MONO = ParagraphStyle('mono', fontName='Courier-Bold', fontSize=7.8,
                      leading=9.4, textColor=INK)
NOTE = ParagraphStyle('note', fontName='Helvetica', fontSize=7.8, leading=9.6,
                      textColor=WARN)
CODE = ParagraphStyle('code', fontName='Courier', fontSize=7.6, leading=9.4,
                      textColor=INK, backColor=CODE_BG,
                      borderPadding=(5, 5, 5, 5), spaceBefore=2, spaceAfter=2)


def band(canvas, doc):
    """Header block and footer rule, drawn straight onto the page."""
    canvas.saveState()
    w, h = A4

    canvas.setFillColor(TEAL)
    canvas.rect(0, h - 33 * mm, w, 33 * mm, stroke=0, fill=1)

    canvas.setFillColor(colors.white)
    canvas.setFont('Helvetica-Bold', 8)
    canvas.drawString(18 * mm, h - 13 * mm, 'HI TECH GROUP')
    canvas.setFont('Helvetica-Bold', 19)
    canvas.drawString(18 * mm, h - 23 * mm, 'Telephony API - Quick Start')
    canvas.setFont('Helvetica', 9)
    canvas.drawString(18 * mm, h - 29.5 * mm,
                      'Everything needed to reach a 200. Full reference attached separately.')

    canvas.setStrokeColor(RULE)
    canvas.setLineWidth(0.5)
    canvas.line(18 * mm, 13 * mm, w - 18 * mm, 13 * mm)
    canvas.setFont('Helvetica', 7)
    canvas.setFillColor(MUTED)
    canvas.drawString(18 * mm, 9 * mm, 'HRX HRMS  |  hrx.hitechgroup.in  |  v1')
    canvas.drawRightString(w - 18 * mm, 9 * mm, 'Confidential')
    canvas.restoreState()


def tbl(rows, widths, head=True):
    t = Table(rows, colWidths=widths, hAlign='LEFT')
    style = [
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 1.9),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 1.9),
        ('LEFTPADDING', (0, 0), (-1, -1), 5),
        ('RIGHTPADDING', (0, 0), (-1, -1), 5),
        ('LINEBELOW', (0, 0), (-1, -2), 0.4, RULE),
        ('BOX', (0, 0), (-1, -1), 0.4, RULE),
    ]
    if head:
        style += [
            ('BACKGROUND', (0, 0), (-1, 0), BAND),
            ('FONT', (0, 0), (-1, 0), 'Helvetica-Bold', 7.8),
            ('TEXTCOLOR', (0, 0), (-1, 0), TEAL),
        ]
    t.setStyle(TableStyle(style))
    return t


def p(text, st=BODY):
    return Paragraph(text, st)


def m(text):
    return Paragraph(text, MONO)


S = []

# ---- 1. endpoint
S.append(p('1. Endpoint', H2))
S.append(Preformatted('https://hrx.hitechgroup.in/api/telephony/v1', CODE))
S.append(Spacer(1, 2))
S.append(tbl([
    ['Path', 'Purpose'],
    [m('GET /health'), p('Test your setup. <b>Start here.</b>', CELL)],
    [m('POST /events'), p('One call event, as it happens', CELL)],
    [m('POST /events/batch'), p('Drain a backlog after an outage (max 500)', CELL)],
    [m('POST /recordings/ready'), p('Tell us a recording is available', CELL)],
    [m('POST /agent-sessions'), p('Agent login, state, break, logout', CELL)],
], [46 * mm, 128 * mm]))

# ---- 2. credentials
S.append(p('2. Credentials', H2))
S.append(tbl([
    [p('API key', CELL), m('&nbsp;')],
    [p('Shared secret', CELL), m('&nbsp;')],
], [32 * mm, 142 * mm], head=False))
S.append(Spacer(1, 1.5))
S.append(p('Sent separately from this document. The shared secret is never transmitted '
           '- it only computes the signature below, which we verify at our end.', BODY))

# ---- 3. authentication
S.append(p('3. Authentication - four headers on every request', H2))
S.append(tbl([
    ['Header', 'Value'],
    [m('Authorization'), p('<font face="Courier">Bearer &lt;api key&gt;</font>', CELL)],
    [m('X-HRX-Timestamp'), p('Unix epoch seconds. Must be within <b>300s</b> of our clock.', CELL)],
    [m('X-HRX-Nonce'), p('A fresh UUID per request. Remembered for 10 minutes.', CELL)],
    [m('X-HRX-Signature'), p('<font face="Courier">sha256=</font> + the HMAC below', CELL)],
], [38 * mm, 136 * mm]))
S.append(Preformatted(
    'signable  = timestamp + "." + nonce + "." + raw_body\n'
    'signature = "sha256=" + hex(HMAC_SHA256(shared_secret, signable))', CODE))
S.append(p('Sign the exact bytes you send. Re-serialising the JSON first changes key '
           'order and whitespace, and every signature then fails - the most common '
           'cause of a 401 here.', NOTE))

# ---- 4. recordings
S.append(p('4. Recordings - two ways, either is fine', H2))
S.append(tbl([
    ['Option', 'What you send', 'Why'],
    [p('<b>A. Signed URL</b><br/><font size="7.4">preferred</font>', CELL),
     p('<font face="Courier">recording_uid</font> only, plus expose '
       '<font face="Courier">GET /recordings/{uid}/signed-url</font>', CELL),
     p('We ask for a short-lived link at play time. Nothing durable is stored, '
       'so a leak is worth little.', CELL)],
    [p('<b>B. Push the link</b>', CELL),
     p('<font face="Courier">recording_url</font> (or <font face="Courier">url</font>), '
       'optionally <font face="Courier">url_expires_at</font>', CELL),
     p('Nothing to build at your end. We store the link and refresh it whenever '
       'you send a newer one.', CELL)],
], [30 * mm, 62 * mm, 82 * mm]))
S.append(Spacer(1, 1.5))
S.append(p('Either way HRX streams the audio itself rather than handing the link to the '
           'browser, so every playback is permission-checked and logged. You may also send '
           'the recording block inside <font face="Courier">call.completed</font> - the CDR '
           'and the audio then arrive in a single request.', BODY))

# ---- 5. what we need
S.append(p('5. Two things we need confirmed in writing', H2))
S.append(tbl([
    [p('<b>1</b>', CELL),
     p('<b>Every call must be reported, including calls dialled manually on the '
       'handset</b> - not only API-placed calls. Inbound, IVR, predictive, callback '
       'and forwarded too. Much of our floor dials manually, so this is go/no-go.', CELL)],
    [p('<b>2</b>', CELL),
     p('<b>Which IP will your webhooks originate from?</b> An allowlist is already '
       'enforced, so anything from an address we have not been told about is refused '
       'with <font face="Courier">403 ip_not_allowed</font>. It is often not the same '
       'host as your agent API - please confirm rather than assume.', CELL)],
], [7 * mm, 167 * mm], head=False))

# ---- 6. responses
S.append(p('6. Responses', H2))
S.append(tbl([
    ['Status', 'Meaning', 'Status', 'Meaning'],
    [m('202'), p('Accepted and stored', CELL),
     m('401'), p('<font face="Courier">stale_timestamp</font> - clock over 300s out, run NTP', CELL)],
    [m('200'), p('Duplicate <font face="Courier">event_id</font>. Safe - do retry', CELL),
     m('401'), p('<font face="Courier">replayed_nonce</font> - reuse of a nonce', CELL)],
    [m('401'), p('<font face="Courier">invalid_api_key</font> - key wrong or missing', CELL),
     m('403'), p('<font face="Courier">ip_not_allowed</font> - tell us your IP', CELL)],
    [m('401'), p('<font face="Courier">invalid_signature</font> - usually re-serialised JSON', CELL),
     m('422'), p('<font face="Courier">validation_failed</font> - field missing or malformed', CELL)],
], [12 * mm, 75 * mm, 12 * mm, 75 * mm]))
S.append(Spacer(1, 1.5))
S.append(p('Every rejection carries a <font face="Courier">request_id</font> - quote it and we '
           'can say exactly what was refused. Send <font face="Courier">event_id</font> as a UUID '
           'and reuse it on retries; that is what makes a retry safe.', BODY))

# ---- 7. first step
S.append(p('7. First step', H2))
S.append(p('Run <font face="Courier">GET /health</font>. A <b>200</b> confirms your key, secret, '
           'clock and source IP at once - please do not send call data until it passes. '
           'The attached <font face="Courier">curl-recording-ready.sh</font> is a runnable signed '
           'example; the pack has the full field list, OpenAPI file and Postman collection.', BODY))

S.append(Spacer(1, 5))
S.append(p('Questions: Nagender Kumar, Hi Tech Group', BODY))


doc = BaseDocTemplate(
    OUT, pagesize=A4,
    leftMargin=18 * mm, rightMargin=18 * mm,
    topMargin=38 * mm, bottomMargin=16 * mm,
    title='HRX Telephony API - Quick Start',
    author='Hi Tech Group',
    subject='Telephony vendor quick start',
)
doc.addPageTemplates([PageTemplate(
    id='only',
    frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='f')],
    onPage=band,
)])
doc.build(S)

# A one-pager that silently becomes two has lost the point of being one.
from pypdf import PdfReader
pages = len(PdfReader(OUT).pages)
print('wrote %s (%.1f KB, %d page%s)'
      % (OUT, os.path.getsize(OUT) / 1024.0, pages, '' if pages == 1 else 's'))
if pages != 1:
    raise SystemExit('FAILED: spilled onto %d pages - tighten the copy' % pages)
