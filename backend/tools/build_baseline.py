#!/usr/bin/env python3
"""Regenerate frozen fingerprint/OpenAPI operation lists (requires PyYAML).

Never run against a modified v1 tree; this is a maintainer tool, not part of runtime.
"""
import argparse
import hashlib
import json
from pathlib import Path
import yaml

parser = argparse.ArgumentParser()
parser.add_argument('--v1-root', type=Path, required=True)
parser.add_argument('--force', action='store_true', help='Overwrite previously frozen baseline')
args = parser.parse_args()
root = args.v1_root.resolve()
out = Path(__file__).resolve().parents[2] / 'docs' / 'backend-rewrite'
fe_out = out/'frontend-fingerprint-v1.2.4.json'
api_out = out/'openapi-operations-v1.2.4.json'
if (fe_out.exists() or api_out.exists()) and not args.force:
    raise SystemExit('Frozen baseline exists; pass --force ONLY after approved baseline change')

sha = lambda raw: hashlib.sha256(raw).hexdigest()
files = sorted(p for p in (root/'frontend').rglob('*') if p.is_file())
entries = [{'path': p.relative_to(root/'frontend').as_posix(), 'sha256': sha(p.read_bytes()), 'size': p.stat().st_size} for p in files]
stream = ''.join(e['path']+'\0'+e['sha256']+'\n' for e in entries).encode()
fe_out.write_text(json.dumps({'frontend_files': len(entries), 'aggregate_sha256': sha(stream), 'files': entries}, ensure_ascii=False, indent=2)+'\n')
api = root/'contracts/openapi/openapi.yaml'
schema = yaml.safe_load(api.read_text())
ops = []
for path, object_ in schema['paths'].items():
    for method in ('get', 'post', 'put', 'patch', 'delete', 'options', 'head'):
        if method not in object_: continue
        op = object_[method]
        ops.append({'method': method.upper(), 'path': path, 'operation_id': op['operationId'],
                    'success_statuses': [int(s) for s in op.get('responses', {}) if s.startswith('2')],
                    'anonymous': op.get('security') == [] or (op.get('security') is None and schema.get('security') == [])})
api_out.write_text(json.dumps({'source':'erp_v1.2.4.zip', 'openapi_sha256': sha(api.read_bytes()), 'operations':ops}, indent=2, ensure_ascii=False)+'\n')
print('Saved frontend files:', len(entries), 'API operations:', len(ops))
