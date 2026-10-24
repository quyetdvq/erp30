#!/usr/bin/env python3
"""Prove unchanged v1.2.4 frontend + OpenAPI schema at a supplied source root."""
import argparse
import hashlib
import json
from pathlib import Path

arg = argparse.ArgumentParser()
arg.add_argument('--v1-root', type=Path, required=True, help='Directory containing frontend/ and contracts/openapi/openapi.yaml')
args = arg.parse_args()
out = Path(__file__).resolve().parents[2] / 'docs' / 'backend-rewrite'
fe = json.loads((out/'frontend-fingerprint-v1.2.4.json').read_text())
api = json.loads((out/'openapi-operations-v1.2.4.json').read_text())

def sha(data): return hashlib.sha256(data).hexdigest()

root = args.v1_root
files = sorted(p for p in (root/'frontend').rglob('*') if p.is_file())
paths = {p.relative_to(root/'frontend').as_posix(): p for p in files}
expected = {item['path']: item['sha256'] for item in fe['files']}
missing = sorted(set(expected) - set(paths))
added = sorted(set(paths) - set(expected))
changed = sorted(path for path in paths.keys() & expected.keys() if sha(paths[path].read_bytes()) != expected[path])
actual_api = root/'contracts/openapi/openapi.yaml'
valid_api = actual_api.is_file() and sha(actual_api.read_bytes()) == api['openapi_sha256']
print('FRONTEND', 'PASS' if not (missing or added or changed) else 'FAIL',
      'checked=', len(paths), 'missing=', len(missing), 'added=', len(added), 'changed=', len(changed))
print('OPENAPI', 'PASS' if valid_api else 'FAIL', 'expected operations=', len(api['operations']))
for reason, seq in [('MISSING', missing),('ADDED', added),('CHANGED', changed)]:
    for path in seq[:30]: print(reason, path)
if missing or added or changed or not valid_api: raise SystemExit(1)
