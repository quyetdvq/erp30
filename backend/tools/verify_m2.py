#!/usr/bin/env python3
"""M2 static regression gate. Does not need Composer, credentials or a running DB."""
from pathlib import Path
import hashlib
import json
import re

root = Path(__file__).resolve().parents[2]
backend = root / 'backend'
app = backend / 'app'
files = [
    app / 'Shared/Read/PositiveId.php',
    app / 'Shared/Read/UtcBusinessDate.php',
    app / 'Shared/Read/FlatHierarchy.php',
    app / 'Modules/Locations/LocationReadRepository.php',
    app / 'Modules/Organization/OrganizationReadRepository.php',
    app / 'Modules/People/PeopleReadRepository.php',
    app / 'Modules/Access/ScopeFactsReader.php',
    app / 'Commands/M2Verify.php',
]
for p in files:
    assert p.is_file(), f'M2 missing implementation: {p}'
print('PASS: M2 modules and read-only CLI verifier present')

# Lock the untouched M1 code/config/HTTP/CSRF and media layout during M2.
manifest = json.loads((root / 'docs/backend-rewrite/m1-runtime-unchanged.json').read_text())
for item in manifest['files']:
    if item['path'] == 'backend/app/Config/Routes.php':
        continue  # M2.1 deliberately registers reviewed READ routes.
    path = root / item['path']
    assert path.is_file() and hashlib.sha256(path.read_bytes()).hexdigest() == item['sha256'], \
        f'M1 changed unexpectedly during M2: {item["path"]}'
print(f'PASS: M1 runtime/config files unchanged except reviewed M2.1 Routes.php')

# M2 repositories remain private and do not register HTTP on their own.
route = (app / 'Config/Routes.php').read_text()
assert 'ScopeFactsReader' not in route and 'LocationReadRepository' not in route
assert 'PeopleReadRepository' not in route and 'OrganizationReadRepository' not in route
assert re.search(r'\$routes->(get|post|put|patch|delete)\(', route)
print('PASS: M2 repositories remain private; M2.1 has separate PBAC HTTP controller')

# Repositories + facts reader are intentionally read-only, no implicit permission decisions.
code = '\n'.join(p.read_text() for p in files if '/Modules/' in str(p))
for forbidden in [
    r'->insert\s*\(', r'->update\s*\(', r'->delete\s*\(',
    r'->replace\s*\(', r'->truncate\s*\(', r'->transStart\s*\(',
    r'->transCommit\s*\(', r'\bauthorize\s*\(',
]:
    assert not re.search(forbidden, code), f'Unexpected M2 mutation/decision token: {forbidden}'
assert 'where(\'status\', \'ACTIVE\')' in (app / 'Modules/People/PeopleReadRepository.php').read_text()
assert 'UtcBusinessDate::at($instant)' in (app / 'Modules/Access/ScopeFactsReader.php').read_text()
assert 'FlatHierarchy::visibleRows' not in route
people = (app / 'Modules/People/PeopleReadRepository.php').read_text()
for predicate in ("->where('status', 'ACTIVE')", "->where('effective_from <=', $date)",
                  "->where('effective_to', null)", "->orWhere('effective_to >=', $date)",
                  "->orderBy('is_primary', 'DESC')", "->orderBy('effective_from', 'DESC')"):
    assert predicate in people, f'M2 effective-assignment predicate changed: {predicate}'
assert 'private const BULK_LIMIT = 500;' in people
organization = (app / 'Modules/Organization/OrganizationReadRepository.php').read_text()
assert "->where('status', 'ACTIVE')" not in organization, 'Do not add an M2 status filter to scope facts'
print('PASS: M2 is read-only, UTC-dated, and contains no authorization decision')

# Verify filenames/layout rule is untouched.
assert (backend / 'public/upload').is_dir()
assert (root / 'backend_basline').is_dir()
assert not (root / 'backend-v2').exists()
print('PASS: agreed backend_basline / backend / backend/public/upload layout retained')
