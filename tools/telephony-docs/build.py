# -*- coding: utf-8 -*-
"""Assemble and render the API reference PDF.

The document is split across three files only because building it in one go
kept running into shell quoting; exec() keeps the shared styles and helpers in
scope without turning them into a package.
"""
import io
import os
import re

HERE = os.path.dirname(os.path.abspath(__file__))

src = io.open(os.path.join(HERE, 'build_api_pdf.py'), encoding='utf-8').read()

# build_api_pdf.py accumulated a partial part-2 from an earlier attempt; keep
# only everything up to the content marker, then add the parts in order.
marker = '# ---- 3. endpoints'
if marker in src:
    src = src[:src.index(marker)]

parts = [src]
for name in ('pdf_part2.py', 'pdf_part3.py'):
    body = io.open(os.path.join(HERE, name), encoding='utf-8').read()
    # Strip the coding line and leading comments; they are not executable.
    parts.append(body)

code_all = '\n'.join(parts)

ns = {'__name__': 'apidoc'}
exec(compile(code_all, 'apidoc', 'exec'), ns)

# ------------------------------------------------------------------ render --
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.platypus import Frame, PageTemplate

DocTemplate = ns['DocTemplate']
cover = ns['cover']
furniture = ns['furniture']
S = ns['S']
OUT = ns['OUT']

doc = DocTemplate(
    OUT, pagesize=A4,
    leftMargin=20 * mm, rightMargin=20 * mm,
    topMargin=20 * mm, bottomMargin=20 * mm,
    title='HRX Telephony Ingest API - Integration Reference',
    author='Hi Tech Group',
    subject='API specification for CentroVision',
)

frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='f')
doc.addPageTemplates([
    PageTemplate(id='cover', frames=[frame], onPage=cover),
    PageTemplate(id='body', frames=[frame], onPage=furniture),
])

# multiBuild so the table of contents resolves its page numbers.
doc.multiBuild(S)

print('wrote %s (%.1f KB)' % (OUT, os.path.getsize(OUT) / 1024))
