#!/usr/bin/env python3
"""Fail-closed v1.2.4 baseline + accepted M8/M9.1 frontend overlay; no writes.

The original frontend/backend-baseline and OpenAPI references remain pinned.
Only the exact reviewed v1.5.0/M9.1 frontend changes are allowed to differ.
"""
import argparse
import json
import re
from pathlib import Path

from locked_snapshot import load_manifest, sha256, verify_directory

parser = argparse.ArgumentParser()
parser.add_argument('--v1-root', type=Path, default=Path(__file__).resolve().parents[2],
                    help='Source root with frontend/ and contracts/openapi/openapi.yaml')
args = parser.parse_args()
root = args.v1_root.resolve()
docs = Path(__file__).resolve().parents[2] / 'docs' / 'backend-rewrite'

# Independent canonical-LF SHA-256 of the reviewed, static manifest.
# Never regenerate this pin from files being verified on a running system.
APPROVED_M91_OVERLAY_SHA256 = '6007cfb802a707b72fa628149881b751c149fe8272b8205f0fa483daf5de7f4a'


def frontend_with_approved_changes(reference: dict[str, str]) -> dict[str, str]:
    path = docs / 'frontend-approved-delta-v1.5.0-m91.json'
    if not path.is_file() or path.is_symlink():
        raise AssertionError('Reviewed M9.1 frontend overlay missing or linked')
    canonical = path.read_bytes().replace(b'\r\n', b'\n')
    if sha256(canonical) != APPROVED_M91_OVERLAY_SHA256:
        raise AssertionError('Reviewed M9.1 frontend overlay was changed')
    document = json.loads(canonical.decode('utf-8'))
    if (document.get('original_baseline') != 'erp_v1.2.4.zip' or
            document.get('reviewed_source') != 'erp_v1.5.0.zip' or
            document.get('accepted_changes') != [
                'M8 product import frontend',
                'M9.1 employee performance',
                'M9.1 E2E assertion correction',
            ]):
        raise AssertionError('Frontend overlay origin or change approvals differ')
    additions, changes = document.get('added'), document.get('changed')
    if not isinstance(additions, list) or len(additions) != 4 or \
            not isinstance(changes, list) or len(changes) != 10:
        raise AssertionError('Unapproved frontend file inventory')
    expected = dict(reference)
    seen = set()
    for kind, entries in (('added', additions), ('changed', changes)):
        for entry in entries:
            if not isinstance(entry, dict) or set(entry) != {'path', 'sha256'}:
                raise AssertionError('Invalid frontend overlay entry')
            name, digest = entry['path'], entry['sha256']
            if (not isinstance(name, str) or not name or name.startswith('/') or
                    '\\' in name or '..' in Path(name).parts or name in seen or
                    not isinstance(digest, str) or not re.fullmatch('[a-f0-9]{64}', digest)):
                raise AssertionError('Invalid frontend overlay path/hash')
            if kind == 'added' and name in reference:
                raise AssertionError('Frontend addition overlaps frozen original')
            if kind == 'changed' and (name not in reference or reference[name] == digest):
                raise AssertionError('Frontend change is not a genuine reviewed difference')
            expected[name] = digest
            seen.add(name)
    if len(expected) != len(reference) + 4:
        raise AssertionError('Frontend approved overlay file count incorrect')
    return expected

try:
    reference = load_manifest(docs / 'frontend-fingerprint-v1.2.4.json')
    # These are local artifacts, not source: ignore only known ROOT-level paths.
    # The built dist/ is scanned independently by m93_release_preflight.py.
    # No src/, public/, e2e/, or scripts/ file is excluded from the source pin.
    # Ignore only the two common .vscode metadata files (not the whole directory).
    # Never ignore unreviewed source or public templates, including Office locks.
    verify_directory(root / 'frontend', frontend_with_approved_changes(reference),
                     'Frontend', reference_label='reviewed v1.5.0/M9.1 source',
                     ignored_root_dirs=('node_modules', 'dist', 'dist-ssr', 'coverage',
                                        'test-results', 'playwright-report', '.git'),
                     ignored_root_files=('.env', '.eslintcache'),
                     ignored_root_prefixes=('.env.',),
                     ignored_root_suffixes=('.tsbuildinfo', '.log'),
                     ignored_local_editor_files=('.vscode/extensions.json',
                                                 '.vscode/settings.json'))
    print('PASS: frontend exact-source fingerprint includes accepted M8/M9.1 changes (281 files)')
    operations = json.loads((docs / 'openapi-operations-v1.2.4.json').read_text(encoding='utf-8'))
    expected_sha = 'f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728'
    if (operations.get('openapi_sha256') != expected_sha or len(operations.get('operations', [])) != 150):
        raise AssertionError('Frozen 150-operation reference manifest changed')
    api = root / 'contracts/openapi/openapi.yaml'
    if not api.is_file() or api.is_symlink() or sha256(api.read_bytes()) != expected_sha:
        raise AssertionError('Original v1.2.4 OpenAPI bytes changed')
    redocly = root / 'contracts/openapi/redocly.yaml'
    redocly_sha = 'dae571142679ca126c9fd69cd81c5b8f09ceb6faf154ea53badbc5096c3f1174'
    if not redocly.is_file() or redocly.is_symlink() or sha256(redocly.read_bytes()) != redocly_sha:
        raise AssertionError('Original v1.2.4 Redocly configuration bytes changed')
    print('PASS: both frozen v1.2.4 OpenAPI files byte-identical; 150 operations')
except (OSError, ValueError, AssertionError) as error:
    raise SystemExit(f'FAIL: {error}') from error
