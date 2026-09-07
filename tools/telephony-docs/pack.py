# -*- coding: utf-8 -*-
"""Repack the vendor pack, and refuse to ship anything that leaks.

The zip is what actually reaches a vendor, so the checks run on the bytes about
to be written rather than on the source tree -- an edit made after the last
sweep cannot slip through between the two.
"""
import io, os, re, sys, zipfile

PACK = 'docs/telephony/vendor-pack'
OUT = 'docs/telephony/HRX-Telephony-Integration-Pack.zip'

ORDER = [
    'START-HERE.md',
    'HRX-Telephony-QuickStart.pdf',
    'INTEGRATION-GUIDE.md',
    'HRX-Telephony-API-Reference.pdf',
    'curl-recording-ready.sh',
    'HRX-Telephony.postman_collection.json',
    'hrms-telephony-webhook-v1.yaml',
    'vendor-required-v1.yaml',
    'HRX-Telephony-UAT.xlsx',
]

missing = [n for n in ORDER if not os.path.exists(os.path.join(PACK, n))]
if missing:
    sys.exit('missing from the pack: %s' % ', '.join(missing))
extra = sorted(set(os.listdir(PACK)) - set(ORDER))
if extra:
    sys.exit('unlisted file in the pack, refusing to guess: %s' % ', '.join(extra))

HEX = '[0-9a-f]'
NOTHEX = '(?<![0-9a-f])'

FORBIDDEN = [
    ('(?i)centrovision', 'a specific vendor name'),
    # An issued API key. Documented examples are allowed below.
    ('hrx_' + HEX + '{32,}', 'an API key'),
    # A shared secret is 64 hex -- but so is every SHA-256 checksum and
    # signature the documentation legitimately shows. Only stop on one that is
    # actually introduced as a credential.
    ('(?i)(secret|password|passwd|token|api.?key)[^0-9a-f]{0,20}'
     + NOTHEX + HEX + '{48,}', 'a credential'),
    ('(?i)DB_PASSWORD|pscp |plink |[.]env[.]deploy', 'internal deployment detail'),
]

# Values that appear in the documentation on purpose. Anything matching a
# credential shape that is not one of these is treated as a real leak.
EXAMPLES = {
    'hrx_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6',
}


def readable(name, blob):
    """Text of a file, reaching inside the compressed formats."""
    if name.endswith('.xlsx'):
        z = zipfile.ZipFile(io.BytesIO(blob))
        return '\n'.join(z.read(n).decode('utf-8', 'ignore')
                         for n in z.namelist() if n.endswith('.xml'))
    if name.endswith('.pdf'):
        from pypdf import PdfReader
        return '\n'.join(pg.extract_text() or ''
                         for pg in PdfReader(io.BytesIO(blob)).pages)
    return blob.decode('utf-8', 'ignore')


def scan(name, blob):
    text = readable(name, blob)
    for pattern, why in FORBIDDEN:
        for m in re.finditer(pattern, text):
            hit = m.group(0)
            if hit in EXAMPLES or any(e in hit for e in EXAMPLES):
                continue
            sys.exit('REFUSING TO PACK %s: contains %s (%r)' % (name, why, hit[:70]))


if os.path.exists(OUT):
    os.remove(OUT)
z = zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED)
for name in ORDER:
    blob = io.open(os.path.join(PACK, name), 'rb').read()
    scan(name, blob)
    z.writestr(name, blob)
    print('  packed %-42s %7d bytes' % (name, len(blob)))
z.close()

print()
print('wrote %s (%.1f KB)' % (OUT, os.path.getsize(OUT) / 1024.0))
print('all %d files scanned for vendor names, credentials and internal detail: clean'
      % len(ORDER))
