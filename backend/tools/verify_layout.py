#!/usr/bin/env python3
"""Repository layout gate; no external dependency, no file writes."""
from pathlib import Path
import hashlib
import json
import re

root = Path(__file__).resolve().parents[2]
docs = root / 'docs' / 'backend-rewrite'

def check(title, ok):
    if not ok:
        raise AssertionError(title)
    print('PASS:', title)

check('approved backend folder names', (root/'backend').is_dir()
      and (root/'backend_basline').is_dir()
      and not (root/'backend-v2').exists()
      and not (root/'backend_baseline').exists())

manifest = json.loads((docs/'backend-basline-fingerprint-v1.2.4.json').read_text(encoding='utf-8'))
base = root/'backend_basline'
actual = {p.relative_to(base).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
          for p in base.rglob('*') if p.is_file()}
expected = {row['path']:row['sha256'] for row in manifest['files']}
check('backend baseline is byte-identical to v1.2.4 (' + str(len(expected)) + ' files)', actual == expected)

upload = root/'backend'/'public'/'upload'
check('approved upload directory exists, without a symlink', upload.is_dir() and not upload.is_symlink())
check('no physical uploads alias/legacy writable source in v2',
      not (root/'backend'/'public'/'uploads').exists()
      and not (root/'backend'/'writable'/'uploads').exists())

rewrite = (root/'backend'/'public'/'.htaccess').read_text()
image_lines = [line.strip() for line in rewrite.splitlines()
               if line.strip().startswith('RewriteRule ^uploads/')]
check('one bounded static-image rewrite inside app public .htaccess', len(image_lines) == 1)
parts = image_lines[0].split(' ')
check('rewrite maps physical files to public/upload, not writable',
      len(parts) == 4 and parts[2] == 'upload/$1/$2'
      and parts[3] == '[L]' and 'writable' not in parts[2])
match = re.compile(parts[1]).fullmatch
for typ,ext in [('product','jpg'),('avatar','png'),('product','webp')]:
    filename = '20261023' + typ + '1234567.' + ext
    url = 'uploads/2026/' + filename
    m = match(url)
    check('rewrite supports safe ' + typ + '/' + ext,
          bool(m) and m.expand(parts[2].replace('$1', r'\1').replace('$2', r'\2'))
          == 'upload/2026/' + filename)
for bad in ('uploads/2026/../.env', 'uploads/2026/name.php',
            'uploads/2026/20261023product1234567.svg',
            'writable/uploads/2026/20261023avatar1234567.jpg',
            'uploads/2026/20261023product1234567.php.jpg',
            'uploads/2026/.htaccess'):
    check('no static-image rewrite for ' + bad, match(bad) is None)

policy = (upload/'.htaccess').read_text()
check('upload public policy disables script handlers and directory browsing',
      'Options -Indexes' in policy and 'SetHandler none' in policy
      and 'RemoveHandler .php' in policy and 'Require all denied' in policy)
check('upload content ignored by git', (upload/'.gitignore').is_file())

print('PASS: new project tree is ready for continued development, not production cutover')
