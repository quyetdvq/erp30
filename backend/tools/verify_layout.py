#!/usr/bin/env python3
"""M6 layout and upload security gate; read-only, no DB or HTTP."""
from pathlib import Path
import re

from locked_snapshot import load_manifest, verify_directory

root = Path(__file__).resolve().parents[2]
docs = root / 'docs/backend-rewrite'


def check(title: str, ok: bool) -> None:
    if not ok:
        raise AssertionError(title)
    print('PASS:', title)


try:
    check('approved backend folder names', (root / 'backend').is_dir()
          and (root / 'backend_baseline').is_dir()
          and not (root / 'backend_basline').exists()
          and not (root / 'backend-v2').exists())
    manifest = load_manifest(docs / 'backend-baseline-fingerprint-v1.2.4.json')
    verify_directory(root / 'backend_baseline', manifest, 'Backend baseline')

    upload = root / 'backend/public/upload'
    check('public/upload is a real directory and is not a symlink',
          upload.is_dir() and not upload.is_symlink())
    check('no physical public/uploads alias', not (root / 'backend/public/uploads').exists())
    # CI4 creates writable/uploads/index.html as a placeholder. It is not a
    # public image store; do not conflate it with publicly served images.
    old = root / 'backend/writable/uploads'
    check('no media bytes in private writable/uploads',
          not old.is_symlink() and (not old.exists() or (
              old.is_dir() and all(p.is_file() and not p.is_symlink()
                                   and p.name == 'index.html' for p in old.iterdir()))))

    rewrite = (root / 'backend/public/.htaccess').read_text(encoding='utf-8')
    lines = [line.strip() for line in rewrite.splitlines()
             if line.strip().startswith('RewriteRule ^uploads/')]
    check('one application-owned static-image rewrite', len(lines) == 1)
    parts = lines[0].split(' ')
    check('image rewrite targets only public/upload, never writable',
          len(parts) == 4 and parts[2] == 'upload/$1/$2'
          and parts[3] == '[L]' and 'writable' not in parts[2])
    match = re.compile(parts[1]).fullmatch
    for typ, ext in [('product', 'jpg'), ('avatar', 'png'), ('product', 'webp')]:
        filename = '20261023' + typ + '1234567.' + ext
        uri = 'uploads/2026/' + filename
        m = match(uri)
        check('rewrite supports ' + typ + '/' + ext,
              bool(m) and m.expand(r'upload/\1/\2') == 'upload/2026/' + filename)
    for bad in ('uploads/2026/../.env', 'uploads/2026/name.php',
                'uploads/2026/20261023product1234567.svg',
                'writable/uploads/2026/20261023avatar1234567.jpg',
                'uploads/2026/20261023product1234567.php.jpg',
                'uploads/2026/.htaccess'):
        check('reject unsafe rewrite: ' + bad, match(bad) is None)

    policy = (upload / '.htaccess').read_text(encoding='utf-8')
    check('public/upload prevents script handling and directory listing',
          'Options -Indexes' in policy and 'SetHandler none' in policy
          and 'RemoveHandler .php' in policy and 'Require all denied' in policy)
    check('media payloads excluded from Git', (upload / '.gitignore').is_file())
    print('PASS: legacy layout gate reconciled; NOT a live Apache or production acceptance')
except (AssertionError, OSError, ValueError) as error:
    raise SystemExit(f'FAIL: {error}') from error
