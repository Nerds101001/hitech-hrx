# -*- coding: utf-8 -*-
"""One page: how to push a recording. Nothing else.

The 22-page reference and the 7-section quick start both exist and both are
too much to hand a developer who has been asked to do exactly one thing. This
page is the curl, the headers, the body, and the replies -- so it can be read
standing up and typed straight in.

Single A4 side, asserted at build time.
"""
import os
import sys

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    BaseDocTemplate, Frame, PageTemplate, Paragraph, Preformatted, Spacer,
    Table, TableStyle,
)

OUT = 'docs/telephony/vendor-pack/HRX-Recording-Push.pdf'
VENDOR = os.environ.get('HRX_DOC_VENDOR', '').strip()

TEAL = colors.HexColor('#00706E')
INK = colors.HexColor('#1F2933')
MUTED = colors.HexColor('#5B6B7B')
RULE = colors.HexColor('#C7D2DC')
CODE_BG = colors.HexColor('#F4F7F9')
BAND = colors.HexColor('#EDF3F6')
WARN = colors.HexColor('#B32020')

BODY = ParagraphStyle('b', fontName='Helvetica', fontSize=8.4, leading=10.6, textColor=INK)
H2 = ParagraphStyle('h', fontName='Helvetica-Bold', fontSize=9.4, leading=11.4,
                    textColor=TEAL, spaceBefore=4.5, spaceAfter=2.4)
CELL = ParagraphStyle('c', fontName='Helvetica', fontSize=7.9, leading=9.5, textColor=INK)
MONO = ParagraphStyle('m', fontName='Courier-Bold', fontSize=7.9, leading=9.5, textColor=INK)
NOTE = ParagraphStyle('n', fontName='Helvetica', fontSize=7.9, leading=9.8, textColor=WARN)
CODE = ParagraphStyle('k', fontName='Courier', fontSize=7.1, leading=8.9, textColor=INK,
                      backColor=CODE_BG, borderPadding=(5, 5, 5, 5),
                      spaceBefore=2, spaceAfter=2)


def furniture(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(TEAL)
    canvas.rect(0, h - 27 * mm, w, 27 * mm, stroke=0, fill=1)
    canvas.setFillColor(colors.white)
    canvas.setFont('Helvetica-Bold', 8)
    canvas.drawString(18 * mm, h - 12 * mm, 'HI TECH GROUP  |  HRX')
    canvas.setFont('Helvetica-Bold', 17)
    canvas.drawString(18 * mm, h - 21 * mm, 'Push a call recording to HRX')

    canvas.setStrokeColor(RULE)
    canvas.setLineWidth(0.5)
    canvas.line(18 * mm, 13 * mm, w - 18 * mm, 13 * mm)
    canvas.setFont('Helvetica', 7)
    canvas.setFillColor(MUTED)
    canvas.drawString(18 * mm, 9 * mm, 'hrx.hitechgroup.in  |  telephony API v1')
    canvas.drawRightString(w - 18 * mm, 9 * mm,
                           'For %s' % VENDOR if VENDOR else 'Confidential')
    canvas.restoreState()


def tbl(rows, widths, head=True):
    t = Table(rows, colWidths=widths, hAlign='LEFT')
    st = [('VALIGN', (0, 0), (-1, -1), 'TOP'),
          ('TOPPADDING', (0, 0), (-1, -1), 1.9),
          ('BOTTOMPADDING', (0, 0), (-1, -1), 1.9),
          ('LEFTPADDING', (0, 0), (-1, -1), 5),
          ('RIGHTPADDING', (0, 0), (-1, -1), 5),
          ('LINEBELOW', (0, 0), (-1, -2), 0.4, RULE),
          ('BOX', (0, 0), (-1, -1), 0.4, RULE)]
    if head:
        st += [('BACKGROUND', (0, 0), (-1, 0), BAND),
               ('FONT', (0, 0), (-1, 0), 'Helvetica-Bold', 7.9),
               ('TEXTCOLOR', (0, 0), (-1, 0), TEAL)]
    t.setStyle(TableStyle(st))
    return t


def p(t, s=BODY):
    return Paragraph(t, s)


S = []

S.append(p('1. The endpoint', H2))
S.append(Preformatted('POST https://hrx.hitechgroup.in/api/telephony/v1/recordings/ready', CODE))

S.append(p('2. Your credentials', H2))
S.append(tbl([[p('API key', CELL), Paragraph('&nbsp;', MONO)],
              [p('Shared secret', CELL), Paragraph('&nbsp;', MONO)]],
             [30 * mm, 144 * mm], head=False))

S.append(p('3. The four headers', H2))
S.append(tbl([
    ['Header', 'Value'],
    [Paragraph('Authorization', MONO), p('<font face="Courier">Bearer &lt;api key&gt;</font>', CELL)],
    [Paragraph('X-HRX-Timestamp', MONO), p('Unix epoch seconds. Within <b>300s</b> of our clock.', CELL)],
    [Paragraph('X-HRX-Nonce', MONO), p('A fresh UUID every request.', CELL)],
    [Paragraph('X-HRX-Signature', MONO), p('<font face="Courier">sha256=</font> + HMAC-SHA256 of the string below', CELL)],
], [34 * mm, 140 * mm]))
S.append(Preformatted(
    'signable  = timestamp + "." + nonce + "." + raw_body\n'
    'signature = "sha256=" + hex(HMAC_SHA256(shared_secret, signable))', CODE))
S.append(p('Sign the exact bytes you send. Re-serialising the JSON changes key order and '
           'whitespace and every signature then fails - the usual cause of a 401.', NOTE))

S.append(p('4. Copy and run', H2))
S.append(Preformatted(r"""API_KEY="hrx_..."
SECRET="..."
URL="https://hrx.hitechgroup.in/api/telephony/v1/recordings/ready"

EVENT_ID=$(uuidgen | tr 'A-Z' 'a-z')
NONCE=$(uuidgen | tr 'A-Z' 'a-z')
TS=$(date +%s)

BODY="{\"event\":\"recording.ready\",\"event_id\":\"$EVENT_ID\",\
\"occurred_at\":\"$(date +%Y-%m-%dT%H:%M:%S%z)\",\
\"call_uid\":\"CV-20260907-000512\",\"recording_uid\":\"REC-20260907-000512\",\
\"recording_url\":\"https://your-gateway/rec/REC-20260907-000512.wav\",\
\"recording_type\":\"call\",\"duration_seconds\":198,\"format\":\"wav\"}"

SIG=$(printf '%s.%s.%s' "$TS" "$NONCE" "$BODY" \
      | openssl dgst -sha256 -hmac "$SECRET" -r | cut -d' ' -f1)

curl -X POST "$URL" \
  -H "Authorization: Bearer $API_KEY" \
  -H "X-HRX-Timestamp: $TS" \
  -H "X-HRX-Nonce: $NONCE" \
  -H "X-HRX-Signature: sha256=$SIG" \
  -H "Content-Type: application/json" \
  --data-raw "$BODY" """, CODE))

S.append(p('5. The fields', H2))
S.append(tbl([
    ['Field', 'Required', 'Notes'],
    [Paragraph('event_id', MONO), p('yes', CELL), p('A UUID. Reuse it on retries - that is what makes a retry safe.', CELL)],
    [Paragraph('call_uid', MONO), p('yes', CELL), p('The same RID/UID shown on your own CDR screen.', CELL)],
    [Paragraph('recording_uid', MONO), p('yes', CELL), p('Your identifier for the recording.', CELL)],
    [Paragraph('recording_url', MONO), p('no', CELL), p('Where we can fetch the audio. <font face="Courier">url</font> is accepted as an alias.', CELL)],
    [Paragraph('url_expires_at', MONO), p('no', CELL), p('If the link above is time-limited, say when it dies.', CELL)],
    [Paragraph('recording_type', MONO), p('no', CELL), p('<font face="Courier">call</font> (default), transfer_leg, conference, voicemail', CELL)],
    [Paragraph('duration_seconds<br/>format, channels<br/>size_bytes', MONO), p('no', CELL),
     p('Metadata. Stereo preferred - agent and customer on separate channels.', CELL)],
], [34 * mm, 16 * mm, 124 * mm]))

S.append(p('6. What you get back', H2))
S.append(tbl([
    ['Code', 'Meaning', 'Code', 'Meaning'],
    [Paragraph('202', MONO), p('Accepted and stored', CELL),
     Paragraph('401', MONO), p('<font face="Courier">stale_timestamp</font> - clock over 300s out', CELL)],
    [Paragraph('200', MONO), p('Duplicate event_id. Safe.', CELL),
     Paragraph('403', MONO), p('<font face="Courier">ip_not_allowed</font> - tell us your source IP', CELL)],
    [Paragraph('401', MONO), p('<font face="Courier">invalid_api_key</font>', CELL),
     Paragraph('422', MONO), p('<font face="Courier">validation_failed</font> - a field is missing', CELL)],
    [Paragraph('401', MONO), p('<font face="Courier">invalid_signature</font>', CELL),
     Paragraph('429', MONO), p('Over 600 requests a minute', CELL)],
], [11 * mm, 74 * mm, 11 * mm, 78 * mm]))

S.append(Spacer(1, 3))
S.append(p('Test with <font face="Courier">GET /api/telephony/v1/health</font> first - same four headers, '
           'empty body. A <b>200</b> confirms your key, secret, clock and source IP together. '
           'Tell us the IP your webhooks come from: an allowlist is enforced and anything else gets a 403.',
           BODY))
S.append(Spacer(1, 4))
S.append(p('Nagender Kumar, Hi Tech Group', BODY))


doc = BaseDocTemplate(
    OUT, pagesize=A4,
    leftMargin=18 * mm, rightMargin=18 * mm,
    topMargin=32 * mm, bottomMargin=16 * mm,
    title='HRX - Push a call recording',
    author='Hi Tech Group',
    subject='Recording push, single page',
)
doc.addPageTemplates([PageTemplate(
    id='only',
    frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='f')],
    onPage=furniture,
)])
doc.build(S)

from pypdf import PdfReader
pages = len(PdfReader(OUT).pages)
print('wrote %s (%.1f KB, %d page%s)'
      % (OUT, os.path.getsize(OUT) / 1024.0, pages, '' if pages == 1 else 's'))
if pages != 1:
    sys.exit('FAILED: spilled onto %d pages' % pages)
