#!/usr/bin/env python3
"""M9.3 no-network/no-database release preflight for ERP v1.5 + M9.1 + M9.2.

READ ONLY. Checks reviewed source, API routes, protected upload layout and
public/build hygiene. --require-build adds production frontend artifact checks.
Not a live authentication, backup/restore or production-cutover attestation.
"""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import subprocess
import sys
from urllib.parse import unquote, urlsplit
from zipfile import BadZipFile, ZipFile

ROOT = Path(__file__).resolve().parents[2]
BLOCKED_SUFFIXES = {'.bak', '.db', '.dump', '.key', '.log', '.map', '.pem', '.pfx',
                    '.php', '.sql', '.sqlite', '.sqlite3', '.swp', '.tmp'}
BLOCKED_DIRS = {'.git', '.svn', '.vscode', '__macosx', '__pycache__',
                'node_modules', 'vendor'}


class PreflightFailure(Exception):
    """An independently checked artifact is missing or unsafe."""


def scan_static_tree(directory: Path) -> None:
    if not directory.is_dir() or directory.is_symlink():
        raise PreflightFailure(f'Missing or linked static directory: {directory}')
    for entry in directory.rglob('*'):
        name = entry.name.lower()
        rel = entry.relative_to(directory)
        if entry.is_symlink():
            raise PreflightFailure(f'Symlink in public files: {rel}')
        # Office/WPS may create both ~$name.xlsx and numbered ~1$name.xlsx locks.
        if (name in BLOCKED_DIRS or re.match(r'^~(?:\d+)?\$', name) or
                name.startswith('.env') or
                name in {'.ds_store', 'thumbs.db', 'id_rsa', 'id_ed25519'} or
                (entry.is_file() and entry.suffix.lower() in BLOCKED_SUFFIXES)):
            raise PreflightFailure(f'Unsafe public artifact: {rel}')
        if not entry.is_dir() and not entry.is_file():
            raise PreflightFailure(f'Unknown public filesystem entry: {rel}')


def check_import_templates(directory: Path) -> None:
    templates = directory / 'import-templates'
    csv_file = templates / 'product-v1.csv'
    xlsx = templates / 'product-v1.xlsx'
    for item in (csv_file, xlsx):
        if not item.is_file() or item.is_symlink() or item.stat().st_size == 0:
            raise PreflightFailure(f'Import template missing/empty: {item}')
    try:
        header = csv_file.read_text(encoding='utf-8-sig').splitlines()[0]
    except (UnicodeError, IndexError) as exc:
        raise PreflightFailure('Invalid CSV import template') from exc
    if not header.startswith('record_type,source_key,product_code,'):
        raise PreflightFailure('Unexpected CSV Product Import template header')
    try:
        with ZipFile(xlsx) as archive:
            entries = set(archive.namelist())
            if not {'[Content_Types].xml', 'xl/workbook.xml',
                    'xl/worksheets/sheet1.xml'} <= entries or archive.testzip() is not None:
                raise PreflightFailure('XLSX Product Import template damaged')
    except BadZipFile as exc:
        raise PreflightFailure('XLSX Product Import template is not valid XLSX') from exc


def check_backend_public() -> None:
    public = ROOT / 'backend/public'
    if not public.is_dir() or public.is_symlink():
        raise PreflightFailure('Backend document root missing/linked')
    for forbidden in ('.env', '.git', 'vendor', 'writable', 'composer.json',
                      'composer.lock', 'config.php'):
        if (public / forbidden).exists() or (public / forbidden).is_symlink():
            raise PreflightFailure(f'Private backend file/dir under public: {forbidden}')
    if not (public / 'index.php').is_file():
        raise PreflightFailure('Missing CodeIgniter public/index.php')


def safe_build_asset(url: str, dist: Path) -> Path | None:
    parsed = urlsplit(url)
    if parsed.scheme or parsed.netloc or not parsed.path.lower().endswith(('.js', '.css')):
        return None
    path = unquote(parsed.path).lstrip('/')
    parts = Path(path).parts
    if not parts or '..' in parts or '\\' in path or parts[0] != 'assets':
        raise PreflightFailure(f'Unexpected compiled asset path: {parsed.path}')
    asset = dist.joinpath(*parts)
    if not asset.is_file() or asset.is_symlink():
        raise PreflightFailure(f'Compiled asset missing: {parsed.path}')
    return asset


def check_build(dist: Path) -> None:
    scan_static_tree(dist)
    index = dist / 'index.html'
    if not index.is_file() or index.is_symlink():
        raise PreflightFailure('Built frontend/index.html missing')
    html = index.read_text(encoding='utf-8')
    if ('/src/main.ts' in html or '@vite/client' in html or
            'vite-plugin-vue-devtools' in html):
        raise PreflightFailure('Development entry/inspector present in built frontend')
    assets = re.findall(r'''(?:src|href)\s*=\s*["']([^"']+)["']''', html)
    compiled = [asset for url in assets if (asset := safe_build_asset(url, dist))]
    if not any(p.suffix == '.js' for p in compiled):
        raise PreflightFailure('Built frontend index has no resolved JS entry')
    htaccess = dist / '.htaccess'
    if (not htaccess.is_file() or htaccess.is_symlink() or
            'RewriteRule ^ index.html [L]' not in htaccess.read_text(encoding='utf-8')):
        raise PreflightFailure('Frontend SPA history fallback missing from dist/.htaccess')
    check_import_templates(dist)


def run_source_gate(name: str, script: str, *args: str) -> None:
    try:
        result = subprocess.run([sys.executable, str(ROOT / 'backend/tools' / script), *args],
                                cwd=ROOT, capture_output=True, text=True, timeout=90,
                                check=False)
    except subprocess.TimeoutExpired as exc:
        raise PreflightFailure(f'{name} exceeded 90 seconds') from exc
    if result.returncode != 0:
        detail = (result.stdout + '\n' + result.stderr).strip()[-2000:]
        raise PreflightFailure(f'{name} failed:\n{detail}')
    print(f'PASS: {name}')


def main() -> int:
    parser = argparse.ArgumentParser(description='Source/build checks; no DB or HTTP')
    parser.add_argument('--require-build', action='store_true',
                        help='Require and inspect frontend/dist after pnpm build')
    args = parser.parse_args()
    try:
        run_source_gate('M8/M9.1 pinned frontend + frozen OpenAPI',
                        'verify_baseline.py', '--v1-root', str(ROOT))
        run_source_gate('150 API route/PBAC source contracts', 'verify_routes.py')
        run_source_gate('backend baseline and safe media layout', 'verify_layout.py')
        check_backend_public()
        print('PASS: no private config under backend/public root')
        static = ROOT / 'frontend/public'
        scan_static_tree(static)
        check_import_templates(static)
        print('PASS: frontend public assets and Product Import templates')
        dist = ROOT / 'frontend/dist'
        if args.require_build:
            if not dist.is_dir():
                raise PreflightFailure('frontend/dist missing: run pnpm build first')
            check_build(dist)
            print('PASS: frontend/dist production artifact shape and hygiene')
        elif dist.exists() or dist.is_symlink():
            scan_static_tree(dist)
            print('PASS: existing frontend/dist contains no blocked public artifacts')
        else:
            print('NOT_TESTED: frontend/dist absent (use --require-build after pnpm build)')
    except (PreflightFailure, OSError, ValueError, UnicodeError) as exc:
        print(f'M9.3 PREFLIGHT: FAIL - {exc}')
        return 1
    print('M9.3 PREFLIGHT: PASS_SOURCE' + ('_AND_BUILD' if args.require_build else ''))
    print('LIMIT: no live HTTP/DB, runtime config, concurrency or restore attestation')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
