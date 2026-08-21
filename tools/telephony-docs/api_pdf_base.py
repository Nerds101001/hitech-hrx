# -*- coding: utf-8 -*-
"""Generate the HRX Telephony Ingest API reference as a PDF.

Built with reportlab's Platypus so the document has real flowing text, tables
that break across pages, and a generated table of contents -- rather than a
markdown file printed to paper.

Every status code, error code, required field and example in here is taken from
the running implementation (VerifyTelephonySignature, TelephonyIngestController
and CallEventNormalizer), not from a design document.

Character set note: the built-in Helvetica/Courier fonts use WinAnsiEncoding,
which has no arrows, check marks or box-drawing characters. Anything outside
latin-1 renders as a black box, so the text below sticks to ASCII plus the few
punctuation marks WinAnsi does carry.
"""
import io
import os

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    BaseDocTemplate, Frame, KeepTogether, NextPageTemplate, PageBreak,
    PageTemplate, Paragraph, Preformatted, Spacer, Table, TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents

OUT = 'docs/telephony/vendor-pack/HRX-Telephony-API-Reference.pdf'

TEAL = colors.HexColor('#00706E')
INK = colors.HexColor('#1F2933')
MUTED = colors.HexColor('#5B6B7B')
RULE = colors.HexColor('#C7D2DC')
CODE_BG = colors.HexColor('#F4F7F9')
BAND = colors.HexColor('#EDF3F6')
WARN = colors.HexColor('#B32020')

# ─────────────────────────────────────────────────────────────── styles ─────

ss = getSampleStyleSheet()


def style(name, **kw):
    base = kw.pop('parent', ss['BodyText'])
    return ParagraphStyle(name, parent=base, **kw)


BODY = style('Body', fontName='Helvetica', fontSize=9.5, leading=14,
             textColor=INK, spaceAfter=7, alignment=TA_LEFT)
LEAD = style('Lead', parent=BODY, fontSize=10.5, leading=16, textColor=MUTED,
             spaceAfter=11)
H1 = style('H1', fontName='Helvetica-Bold', fontSize=17, leading=21,
           textColor=TEAL, spaceBefore=4, spaceAfter=10)
H2 = style('H2', fontName='Helvetica-Bold', fontSize=12.5, leading=16,
           textColor=INK, spaceBefore=15, spaceAfter=6)
H3 = style('H3', fontName='Helvetica-Bold', fontSize=10.5, leading=14,
           textColor=TEAL, spaceBefore=11, spaceAfter=4)
CODE = style('Code', fontName='Courier', fontSize=8, leading=10.8,
             textColor=INK, backColor=CODE_BG, borderPadding=7,
             spaceBefore=4, spaceAfter=9)
CELL = style('Cell', fontName='Helvetica', fontSize=8, leading=11, textColor=INK)
CELLB = style('CellB', parent=CELL, fontName='Helvetica-Bold')
CELLC = style('CellC', parent=CELL, fontName='Courier', fontSize=7.6)
NOTE = style('Note', parent=BODY, fontSize=9, leading=13, textColor=MUTED,
             leftIndent=9, spaceBefore=3)


class DocTemplate(BaseDocTemplate):
    """Adds a TOC hook and page furniture."""

    def afterFlowable(self, flowable):
        if not isinstance(flowable, Paragraph):
            return
        st = flowable.style.name
        # The contents page must not list itself.
        if flowable.getPlainText().strip() == 'Contents':
            return
        if st == 'H1':
            self.notify('TOCEntry', (0, flowable.getPlainText(), self.page))
        elif st == 'H2':
            self.notify('TOCEntry', (1, flowable.getPlainText(), self.page))


def furniture(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFont('Helvetica', 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(20 * mm, h - 12 * mm, 'HRX Telephony Ingest API  |  Version 1.0')
    canvas.drawRightString(w - 20 * mm, h - 12 * mm, 'Hi Tech Group')
    canvas.setStrokeColor(RULE)
    canvas.setLineWidth(0.5)
    canvas.line(20 * mm, h - 14 * mm, w - 20 * mm, h - 14 * mm)
    canvas.line(20 * mm, 15 * mm, w - 20 * mm, 15 * mm)
    canvas.drawString(20 * mm, 11 * mm, 'Confidential - for CentroVision integration use')
    canvas.drawRightString(w - 20 * mm, 11 * mm, 'Page %d' % doc.page)
    canvas.restoreState()


def cover(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(TEAL)
    canvas.rect(0, h - 92 * mm, w, 92 * mm, stroke=0, fill=1)
    canvas.setFillColor(colors.white)
    canvas.setFont('Helvetica-Bold', 11)
    canvas.drawString(20 * mm, h - 30 * mm, 'HI TECH GROUP')
    canvas.setFont('Helvetica-Bold', 27)
    canvas.drawString(20 * mm, h - 50 * mm, 'Telephony Ingest API')
    canvas.setFont('Helvetica', 13)
    canvas.drawString(20 * mm, h - 61 * mm, 'Integration reference for CentroVision')
    canvas.setFont('Helvetica', 9.5)
    canvas.drawString(20 * mm, h - 76 * mm, 'Version 1.0    |    HRX HRMS    |    hrx.hitechgroup.in')

    canvas.setFillColor(MUTED)
    canvas.setFont('Helvetica', 8)
    canvas.drawString(20 * mm, 20 * mm,
                      'Confidential. Issued to CentroVision for the purpose of integrating with HRX.')
    canvas.restoreState()


# ─────────────────────────────────────────────────────────── helpers ────────

def p(text, st=BODY):
    return Paragraph(text, st)


def code(text):
    return Preformatted(text.strip('\n'), CODE)


def table(rows, widths, header=True, zebra=True):
    data = []
    for r in rows:
        data.append([c if hasattr(c, 'wrap') else p(str(c), CELL) for c in r])

    t = Table(data, colWidths=widths, repeatRows=1 if header else 0, hAlign='LEFT')
    cmds = [
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LINEBELOW', (0, 0), (-1, -2), 0.4, RULE),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
    ]
    if header:
        cmds += [
            ('BACKGROUND', (0, 0), (-1, 0), TEAL),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, 0), 8),
            ('LINEBELOW', (0, 0), (-1, 0), 0, colors.white),
        ]
        if zebra:
            for i in range(1, len(data)):
                if i % 2 == 0:
                    cmds.append(('BACKGROUND', (0, i), (-1, i), BAND))
    t.setStyle(TableStyle(cmds))
    return t


def hdr(cells):
    return [p(c, CELLB) for c in cells]


def callout(title, body, tone=TEAL):
    inner = [[p('<b>%s</b>' % title, CELL), ], [p(body, CELL)]]
    t = Table(inner, colWidths=[152 * mm], hAlign='LEFT')
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), BAND),
        ('LINEBEFORE', (0, 0), (0, -1), 3, tone),
        ('LEFTPADDING', (0, 0), (-1, -1), 9),
        ('RIGHTPADDING', (0, 0), (-1, -1), 9),
        ('TOPPADDING', (0, 0), (-1, 0), 7),
        ('BOTTOMPADDING', (0, -1), (-1, -1), 7),
        ('TOPPADDING', (0, 1), (-1, -1), 0),
    ]))
    return t


# ══════════════════════════════════════════════════════════════ content ═════

S = []          # story
W = 170 * mm    # usable width


def section(title):
    S.append(PageBreak())
    S.append(p(title, H1))


# ---- cover ---------------------------------------------------------------
S.append(Spacer(1, 150 * mm))
S.append(p('This document specifies the API that HRX publishes for CentroVision '
           'to send call data into. It is generated from the running '
           'implementation: every status code, error code, required field and '
           'example below is enforced by the live endpoint.', LEAD))
S.append(NextPageTemplate('body'))

# ---- contents ------------------------------------------------------------
S.append(PageBreak())
S.append(p('Contents', H1))
toc = TableOfContents()
toc.levelStyles = [
    ParagraphStyle('T1', fontName='Helvetica-Bold', fontSize=10, leading=18,
                   textColor=INK),
    ParagraphStyle('T2', fontName='Helvetica', fontSize=9, leading=14,
                   leftIndent=12, textColor=MUTED),
]
S.append(toc)

# ---- 1. overview ---------------------------------------------------------
section('1. Overview')
S.append(p('When a call happens on the CentroVision gateway, send a small JSON '
           'object describing it to the HRX endpoint. That is the entire '
           'integration. HRX stores, normalises and reports on the data; '
           'CentroVision is not asked to build reports, storage or any user '
           'interface.', BODY))

S.append(p('Base URL', H3))
S.append(code('https://hrx.hitechgroup.in/api/telephony/v1'))
S.append(p('There is no separate test host. Integrating against the live '
           'endpoint is safe: until a key is issued the endpoint refuses every '
           'request, and once issued, test events land in a call log that '
           'starts empty and affects nothing else in the HR system.', BODY))

S.append(p('Two channels', H2))
S.append(p('The integration uses webhook push as the primary channel, with a '
           'scheduled pull as a safety net. Push alone is fast but lossy: one '
           'network fault and those calls are gone with nothing to indicate '
           'they existed. Pull alone is complete but blind: no live call popup '
           'and no disposition prompt at hangup. Together they give real-time '
           'behaviour plus a daily proof that both systems agree.', BODY))

S.append(table([
    hdr(['Channel', 'Direction', 'When', 'Carries']),
    ['PUSH (primary)', 'CentroVision to HRX', 'As each event happens',
     'Call lifecycle, transfers, conferences, forwards, queue events, '
     'recordings, agent sessions'],
    ['PULL (audit)', 'HRX to CentroVision', 'Nightly at 02:00 IST',
     'Full CDR for the previous day, to detect and backfill anything the push '
     'channel never delivered'],
], [30 * mm, 34 * mm, 32 * mm, 74 * mm]))

S.append(Spacer(1, 5))
S.append(callout(
    'The requirement that matters most',
    'Every call must be reported, including calls dialled manually on the '
    'handset or SIM - not only calls originated through an API request. '
    'Inbound, IVR, predictive, callback and forwarded calls must all raise the '
    'same events, with the "origin" field set correctly. A manual dial sends '
    'exactly the same payload with origin set to manual_dial and ref_id null. '
    'This is acceptance test UAT-05 and it is a go/no-go item.', WARN))

# ---- 2. authentication ---------------------------------------------------
section('2. Authentication')
S.append(p('Every request carries four headers. Three prove identity and '
           'integrity; the fourth is optional but recommended.', BODY))

S.append(table([
    hdr(['Header', 'Value', 'Required']),
    [p('Authorization', CELLC), 'Bearer &lt;api_key&gt;', 'Yes'],
    [p('X-HRX-Timestamp', CELLC), 'Unix time in seconds (integer, not milliseconds)', 'Yes'],
    [p('X-HRX-Nonce', CELLC), 'A value unique to this request. A UUID is ideal.', 'Yes'],
    [p('X-HRX-Signature', CELLC), 'sha256=&lt;lowercase hex HMAC&gt;', 'Yes'],
    [p('Idempotency-Key', CELLC), "The event's event_id", 'Recommended'],
    [p('Content-Type', CELLC), 'application/json', 'Yes'],
], [40 * mm, 90 * mm, 22 * mm]))

S.append(p('Computing the signature', H2))
S.append(code("""
signable  = timestamp + "." + nonce + "." + raw_request_body
signature = "sha256=" + lowercase_hex( HMAC_SHA256(shared_secret, signable) )
"""))

S.append(p('Three rules', H3))
S.append(p('<b>1. Sign the exact bytes you are about to send.</b> Build the JSON '
           'string once, sign that string, transmit that string. Do not '
           'serialise, sign, then serialise again - key order or whitespace '
           'will differ by a character and every signature will fail. This is '
           'the single most common cause of a rejected request.', BODY))
S.append(p('<b>2. For GET /health the body is an empty string</b>, so the '
           'signable value ends with a dot: "1755691200.abc-123." Note the '
           'trailing dot.', BODY))
S.append(p('<b>3. Use a fresh nonce on every HTTP request</b>, including retries '
           'of the same event. HRX remembers nonces for 10 minutes and refuses '
           'reuse.', BODY))

S.append(p('Worked example', H3))
S.append(code("""
shared_secret : s3cr3t
timestamp     : 1755691200
nonce         : 4f8c1a2e-6b3d-4e7a-9c15-2d8f0b6a1e93
body          : {"a":1}

signable      : 1755691200.4f8c1a2e-6b3d-4e7a-9c15-2d8f0b6a1e93.{"a":1}
"""))

S.append(p('Clock skew, replay and source address', H2))
S.append(table([
    hdr(['Control', 'Rule', 'Refusal']),
    ['Clock skew', 'Timestamp must be within 300 seconds of HRX server time',
     p('stale_timestamp', CELLC)],
    ['Replay', 'A nonce is remembered for 10 minutes', p('replayed_nonce', CELLC)],
    ['Source IP', 'Optional allowlist, set per credential', p('ip_not_allowed', CELLC)],
    ['Body size', 'Maximum 5 MB per request', p('payload_too_large', CELLC)],
    ['Rate', '600 requests per minute per source address', p('HTTP 429', CELLC)],
], [30 * mm, 92 * mm, 36 * mm]))
S.append(p('HRX compares signatures in constant time. Please do the same when '
           'verifying anything at your end.', NOTE))
