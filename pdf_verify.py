
import sys, re
sys.stdout.reconfigure(encoding='utf-8')

with open(r'c:/xampp/htdocs/fullstackpayroll/docs/payroll-requirements/Detail payrol.pdf', 'rb') as f:
    raw = f.read()

# Extract all UTF-16LE encoded text blocks
# Find all positions where readable text blocks start
results = []

# Search for key text markers in UTF-16LE
search_terms = [
    (b'P\x00e\x00r\x00i\x00o\x00d\x00e', 'Periode'),
    (b'2\x008\x00', '28'),
    (b'2\x007\x00', '27'),
    (b'B\x00P\x00J\x00S', 'BPJS'),
    (b'K\x00T\x00', 'KT'),
    (b'J\x00S\x00', 'JS'),
    (b'P\x00P\x00h\x00', 'PPh'),
    (b'P\x00P\x00H\x00', 'PPH'),
    (b'K\x00e\x00t\x00e\x00n\x00a\x00g\x00a\x00k\x00e\x00r\x00j\x00a\x00a\x00n', 'Ketenagakerjaan'),
    (b'K\x00e\x00s\x00e\x00h\x00a\x00t\x00a\x00n', 'Kesehatan'),
    (b'J\x00a\x00m\x00i\x00n\x00a\x00n', 'Jaminan'),
    (b'T\x00a\x00n\x00g\x00g\x00a\x00l', 'Tanggal'),
    (b'b\x00u\x00l\x00a\x00n', 'bulan'),
    (b'C\x00a\x00t\x00a\x00t\x00a\x00n', 'Catatan'),
    (b'P\x00e\x00n\x00t\x00i\x00n\x00g', 'Penting'),
    (b'H\x00u\x00b\x00u\x00n\x00g\x00a\x00n', 'Hubungan'),
    (b'S\x00c\x00h\x00e\x00d\x00u\x00l\x00e', 'Schedule'),
    (b'J\x00a\x00d\x00w\x00a\x00l', 'Jadwal'),
    (b'P\x00e\x00n\x00g\x00g\x00a\x00j\x00i\x00a\x00n', 'Penggajian'),
]

print('=== PDF TEXT EXTRACTION (UTF-16LE blocks) ===')
found = {}
for pattern, label in search_terms:
    pos = 0
    while True:
        idx = raw.find(pattern, pos)
        if idx == -1:
            break
        # Extract context: 80 bytes before, 300 after
        start = max(0, idx - 80)
        end = min(len(raw), idx + 300)
        chunk = raw[start:end]
        try:
            decoded = chunk.decode('utf-16-le', errors='replace')
            # Remove null bytes and control chars
            cleaned = ''.join(c for c in decoded if c.isprintable() or c == ' ')
            cleaned = cleaned.replace('\x00', '').strip()
            if len(cleaned) > 8 and label not in found:
                found[label] = cleaned[:200]
                print('[' + label + '] pos=' + str(idx) + ': ' + cleaned[:200])
        except:
            pass
        pos = idx + len(pattern)

print()
print('=== SECTION HEADINGS FOUND ===')
# Extract all bookmarked title entries from PDF structure
title_pattern = re.compile(b'\\xff\\xfeT\x00i\x00t\x00l\x00e\x00.*?(?=\\xff\\xfe|endobj)', re.DOTALL)
matches = title_pattern.findall(raw)
for m in matches[:20]:
    try:
        t = m.decode('utf-16-le', errors='replace')
        t = ''.join(c for c in t if c.isprintable()).strip()
        if len(t) > 3:
            print(' TITLE: ' + t[:150])
    except:
        pass
