#!/usr/bin/env python3
"""Fail-closed v1.2.4 Frontend and OpenAPI immutable-source audit; no writes."""
import argparse
import json
from pathlib import Path

from locked_snapshot import load_manifest, sha256, verify_directory

parser = argparse.ArgumentParser()
parser.add_argument('--v1-root', type=Path, default=Path(__file__).resolve().parents[2],
                    help='Source root with frontend/ and contracts/openapi/openapi.yaml')
args = parser.parse_args()
root = args.v1_root.resolve()
docs = Path(__file__).resolve().parents[2] / 'docs' / 'backend-rewrite'

try:
    reference = load_manifest(docs / 'frontend-fingerprint-v1.2.4.json')
    verify_directory(root / 'frontend', reference, 'Frontend')
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
