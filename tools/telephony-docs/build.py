# -*- coding: utf-8 -*-
"""Assemble and render the telephony API reference PDF.

    python tools/telephony-docs/build.py                 # generic
    python tools/telephony-docs/build.py "Acme Telecom"  # addressed to one vendor

The content lives in three files that are exec'd in order into one namespace,
so the styles and helpers defined in api_pdf_base.py stay in scope for the two
content files without turning the directory into an importable package.

The default output is deliberately vendor-neutral: the same PDF goes to every
telephony vendor being onboarded, and a document addressed to one of them reads
as a copy-paste when it lands with another. Passing a name stamps the cover and
footer only -- the body copy stays in the second person throughout.
"""
import io
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))

# Read before the content files are exec'd; api_pdf_base.py picks it up at
# import time to decide the cover and footer wording.
if len(sys.argv) > 1:
    os.environ['HRX_DOC_VENDOR'] = sys.argv[1]

PARTS = ('api_pdf_base.py', 'api_pdf_endpoints.py', 'api_pdf_reference.py')

source = '\n'.join(
    io.open(os.path.join(HERE, name), encoding='utf-8').read() for name in PARTS
)

ns = {'__name__': 'apidoc'}
exec(compile(source, 'apidoc', 'exec'), ns)

# ------------------------------------------------------------------ render --
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.platypus import Frame, PageTemplate

vendor = os.environ.get('HRX_DOC_VENDOR', '').strip()

doc = ns['DocTemplate'](
    ns['OUT'], pagesize=A4,
    leftMargin=20 * mm, rightMargin=20 * mm,
    topMargin=20 * mm, bottomMargin=20 * mm,
    title='HRX Telephony Ingest API - Integration Reference',
    author='Hi Tech Group',
    subject='API specification for %s' % (vendor or 'telephony vendor integration'),
)

frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='f')
doc.addPageTemplates([
    PageTemplate(id='cover', frames=[frame], onPage=ns['cover']),
    PageTemplate(id='body', frames=[frame], onPage=ns['furniture']),
])

# multiBuild so the table of contents resolves its page numbers.
doc.multiBuild(ns['S'])

print('wrote %s (%.1f KB)%s' % (
    ns['OUT'], os.path.getsize(ns['OUT']) / 1024.0,
    ' for %s' % vendor if vendor else ' (generic)',
))
