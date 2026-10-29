#!/usr/bin/env python3
"""Verify 180 frozen API routes + explicitly approved additions and P1 150 OpenAPI pin.

Explicit domain modules are checked against the frozen v1.9.23 runtime
registry (including P2 and M11), while the original 150 method/path/handler
checksum remains unchanged. No Composer, DB, HTTP or PHP bootstrap.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
import re

root = Path(__file__).resolve().parents[2]
config = root / 'backend/app/Config'
app = root / 'backend/app'
manifest = json.loads((root / 'docs/backend-rewrite/openapi-operations-v1.2.4.json').read_text())
EXPECTED_COUNT = 150
# Pinned M6.4A route footprint: method/path and resolved handler, including modules.
# Change only when an additional reviewed frozen operation has been implemented.
FROZEN_V140_FOOTPRINT = '145e2da8075c3a1874c0bc44d5df5c8b341eb1a41fb4f37a783006a0293e80a3'

ROUTE = re.compile(
    r"\$routes->(?P<verb>get|post|patch|put|delete)\s*\(\s*'(?P<uri>[^']+)'\s*,\s*"
    r"\[\s*(?P<controller>\\?App\\[A-Za-z0-9_\\]+|[A-Za-z_][A-Za-z0-9_]*)::class\s*,\s*"
    r"'(?P<handler>[A-Za-z_][A-Za-z0-9_]*(?:/\$[1-9])?)'\s*\]\s*\)\s*;"
)
ROUTE_CALL = re.compile(r'\$routes->(?:get|post|put|patch|delete)\s*\(')
INCLUDE = re.compile(r"\brequire\s+__DIR__\s*\.\s*'/Routes/ApiV1/([A-Za-z]+Routes\.php)'\s*;")


def fail(message: str) -> None:
    raise AssertionError(message)


def without_comments(value: str) -> str:
    value = re.sub(r'/\*.*?\*/', '', value, flags=re.S)
    return re.sub(r'(?m)^\s*//[^\n]*$', '', value)


def matches(source_path: str, baseline_path: str) -> bool:
    source = ('/api/v1/' + source_path).split('/')
    reference = baseline_path.split('/')
    return len(source) == len(reference) and all(
        (one == other if one not in ('(:num)', '(:segment)') else
         other.startswith('{') and other.endswith('}'))
        for one, other in zip(source, reference)
    )


def parse() -> tuple[dict[tuple[str, str], str], list[str], list[str], int]:
    main = config / 'Routes.php'
    main_text = main.read_text(encoding='utf-8')
    include_names = INCLUDE.findall(without_comments(main_text))
    if len(set(include_names)) != len(include_names) or not include_names:
        fail('Duplicate or absent reviewed module route includes')
    # Domain ownership replaces milestone paths, but not the frozen API.
    approved = json.loads((root / 'backend/tests/fixtures/routes_extensions_approved.json').read_text())
    if approved.get('schema_version') != 1 or approved.get('baseline') != 'v1.9.23':
        fail('Unreviewed route extension manifest version')
    new_modules = approved.get('modules')
    new_routes = approved.get('routes')
    if not isinstance(new_modules, list) or not isinstance(new_routes, list):
        fail('Unreviewed route extension manifest fields')
    if len(set(new_modules)) != len(new_modules) or any(
            not isinstance(name, str) or
            not re.fullmatch(r'[A-Z][A-Za-z0-9]*Routes\.php', name) for name in new_modules):
        fail('Invalid or duplicate approved route module')
    expected_files = {
        'IdentityRoutes.php', 'AccessRoutes.php', 'SettingsRoutes.php',
        'NumberingRoutes.php', 'WorkflowRoutes.php', 'LocationsRoutes.php',
        'OrganizationsRoutes.php', 'PeopleRoutes.php', 'ContactsRoutes.php',
        'ProductsRoutes.php', 'PricingRoutes.php', 'ImportsRoutes.php',
        'CustomersRoutes.php', 'NotificationsRoutes.php',
    }
    expected_files.update(new_modules)
    if set(include_names) != expected_files:
        fail(f'Route include inventory changed: {sorted(set(include_names) ^ expected_files)}')
    if "group('api/v1'" not in main_text or '$routes->setAutoRoute(false)' not in main_text:
        fail('Explicit versioned API group or auto-route shutdown missing')
    if 'service(\'auth\')->routes' in main_text:
        fail('Shield web routes must never be exposed')
    if main_text.count('$routes->options(') != 2:
        fail('Reviewed OPTIONS policy changed')
    module_files = [main] + [config / 'Routes' / 'ApiV1' / name for name in include_names]
    for child in module_files:
        if not child.is_file() or child.is_symlink():
            fail(f'Missing or unsafe route source: {child.name}')
    frozen = manifest.get('operations', [])
    if len(frozen) != 150 or manifest.get('openapi_sha256') != (
            'f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728'):
        fail('Frozen baseline manifest is not the reviewed 150-operation reference')
    reference = {}
    for op in frozen:
        key = (op['method'], op['path'])
        if key in reference:
            fail('Duplicate frozen operation')
        reference[key] = op
    all_baseline = json.loads((root / 'backend/tests/fixtures/routes_v1923.json').read_text())
    expected_all = {
        (entry['method'], entry['path']): (
            entry['handler'][0], entry['handler'][1], entry['options'])
        for entry in all_baseline['routes'] if entry['method'] != 'OPTIONS'
    }
    if len(expected_all) != 178:
        fail('Missing independent 180-route v1.9.23 registry fixture')
    for entry in new_routes:
        if not isinstance(entry, dict) or set(entry) != {'method','path','handler','options'}:
            fail('Invalid approved extra route signature')
        method, path = entry['method'], entry['path']
        handler, options = entry['handler'], entry['options']
        if (method not in {'GET','POST','PUT','PATCH','DELETE'}
                or not isinstance(path, str) or not re.fullmatch(r'api/v1/[a-z0-9_./():-]+', path)
                or not isinstance(handler, list) or len(handler) != 2
                or not isinstance(handler[0], str) or not handler[0].startswith('App\\')
                or not isinstance(handler[1], str)
                or not re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]*(?:/\$[1-9])?', handler[1])
                or options != {'filter':'cors'}):
            fail('Unreviewed approved extension signature')
        key = (method, path)
        if key in expected_all:
            fail(f'Duplicate approved extension: {key}')
        expected_all[key] = (handler[0], handler[1], options)
    registered = {}
    seen_all = set()
    footprint = []
    for source in module_files:
        text = without_comments(source.read_text(encoding='utf-8'))
        import_map = {}
        for namespace in re.findall(r'(?m)^use\s+(App\\[A-Za-z0-9_\\]+);', text):
            import_map[namespace.rsplit('\\', 1)[-1]] = namespace
        registrations = list(ROUTE.finditer(text))
        if len(registrations) != len(ROUTE_CALL.findall(text)):
            fail(f'Unreviewed or unparseable HTTP route declaration in {source.name}')
        for row in registrations:
            verb, raw = row['verb'].upper(), row['uri']
            full_key = (verb, 'api/v1/' + raw)
            if full_key not in expected_all or full_key in seen_all:
                fail(f'Unreviewed or duplicate route: {full_key}')
            seen_all.add(full_key)
            candidates = [key for key in reference if key[0] == verb and matches(raw, key[1])]
            if len(candidates) > 1:
                fail(f'Ambiguous frozen OpenAPI route: {verb} {raw}')
            name = row['controller']
            fqcn = name.lstrip('\\') if name.startswith('\\') else import_map.get(name)
            if not fqcn or not fqcn.startswith('App\\'):
                fail(f'Missing controller import in {source.name}: {name}')
            file = app / (fqcn[4:].replace('\\', '/') + '.php')
            if not file.is_file() or file.is_symlink():
                fail(f'Missing controller: {fqcn}')
            method = row['handler'].split('/')[0]
            handler_text = without_comments(file.read_text(encoding='utf-8'))
            if not re.search(r'\bpublic\s+function\s+' + re.escape(method) + r'\s*\(', handler_text):
                fail(f'Unknown/nonpublic handler: {fqcn}::{method}')
            frozen_entry = expected_all[full_key]
            if (fqcn, row['handler'], {'filter': 'cors'}) != frozen_entry:
                fail(f'v1.9.23 handler/filter changed: {full_key}')
            if candidates:
                key = candidates[0]
                if key in registered:
                    fail(f'Duplicate frozen route: {key}')
                registered[key] = fqcn + '::' + row['handler']
                footprint.append(f'{verb} {key[1]} #{registered[key]}')
    if seen_all != set(expected_all):
        fail(f'Incomplete reviewed route registry ({len(seen_all)}/{len(expected_all)})')
    return registered, footprint, sorted(f'{key[0]} {key[1]}' for key in reference.keys() - registered.keys()), len(new_routes)


def verify_security() -> None:
    for filename, needle in [
        ('Routing.php', 'public bool $autoRoute = false;'),
        ('Routing.php', 'public bool $useControllerAttributes = false;'),
        ('Filters.php', "'csrf'"), ('Filters.php', "'requestmeta'"),
        ('Security.php', "'session'"), ('Security.php', "'X-CSRF-TOKEN'"),
        ('Security.php', 'public bool $regenerate = true;'),
        ('Security.php', 'public bool $redirect = false;'),
    ]:
        if needle not in (config / filename).read_text(encoding='utf-8'):
            fail(f'Reviewed security invariant missing: {filename}: {needle}')
    if "'routes'," in (config / 'Modules.php').read_text(encoding='utf-8'):
        fail('Vendor routes discovery must remain disabled')
    read_controller = (app / 'Controllers/Api/ReadCatalogController.php').read_text()
    bridge = (app / 'Modules/Access/ReadAccessBridge.php').read_text()
    gate = (app / 'Modules/Access/ReadViewGate.php').read_text()
    for needle in ('locationSqlService($user, $instant)->list(',
                   'locationSqlService($user)->show(',
                   "in_array($resource, ['province', 'ward'], true)",
                   "in_array($resource, ['employee', 'department', 'position'], true)",
                   "in_array($resource, ['organization', 'employee_assignment'], true)",
                   'peopleSqlService($user, $instant)->list(',
                   'organizationSqlService($user, $instant)->list(',
                   "FlatHierarchy::visibleRows($page['data'])"):
        if needle not in read_controller:
            fail(f'Canonical SQL read dispatch missing: {needle}')
    for needle in ('new UnifiedAccessService(', 'func_num_args() > 3',
                   '->canView($type, $persistedRow)'):
        if needle not in bridge:
            fail(f'Canonical VIEW bridge changed: {needle}')
    for forbidden in ('DINKERP_M32_READ_MODE', 'DINKERP_M86B_SQL_SCOPE_MODE',
                      'DINKERP_M86C_SQL_SCOPE_MODE', 'organizationSqlEnabled(',
                      'peopleSqlEnabled('):
        if forbidden in read_controller + bridge:
            fail(f'Obsolete runtime authorization mode remains: {forbidden}')
    for needle in ('BasePermissionReader', 'ScopeFactsReader', 'PolicyReadLanes::allows',
                   "BINARY p.status = 'ACTIVE'"):
        if needle not in gate:
            fail(f'PBAC gate missing: {needle}')


try:
    implemented, footprint, missing, approved_count = parse()
    if len(implemented) != EXPECTED_COUNT:
        fail(f'Expected {EXPECTED_COUNT} reviewed routes, found {len(implemented)}')
    digest = hashlib.sha256('\n'.join(sorted(footprint)).encode('utf-8')).hexdigest()
    if digest != FROZEN_V140_FOOTPRINT:
        fail(f'Pinned v1.4.0 route method/path/handler footprint changed: {digest}')
    verify_security()
    if len(missing) != 0:
        fail(f'Expected 0 unimplemented frozen routes, found {len(missing)}')
    print(f'PASS: {len(implemented)}/150 M8 routes match frozen operation contracts and real handlers')
    print(f'PASS: 180 frozen + {approved_count} approved routes; exact method/path/handler/filter signatures')
    print('PASS: M2 PBAC bridge, CI4 route visibility and session CSRF settings retained')
except (AssertionError, OSError, ValueError, KeyError) as error:
    raise SystemExit(f'FAIL: {error}') from error
