#!/usr/bin/env python3
"""M8 CI4 route inventory against the 150 frozen OpenAPI operations.

Traverses the explicit API group and the M4-M7 route files it includes. It
rejects unrecognized registrations, duplicate paths and missing handlers.
No PHP/CI4 bootstrap, database access, or web requests.
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
    r"'(?P<handler>[A-Za-z_][A-Za-z0-9_]*)'\s*\]\s*\)\s*;"
)
ROUTE_CALL = re.compile(r'\$routes->(?:get|post|put|patch|delete)\s*\(')
INCLUDE = re.compile(r"\brequire\s+__DIR__\s*\.\s*'/([^/']+Routes\.php)'\s*;")


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


def parse() -> tuple[dict[tuple[str, str], str], list[str], list[str]]:
    main = config / 'Routes.php'
    main_text = main.read_text(encoding='utf-8')
    include_names = INCLUDE.findall(without_comments(main_text))
    if len(set(include_names)) != len(include_names) or not include_names:
        fail('Duplicate or absent reviewed module route includes')
    # The v1.4.0 API footprint explicitly includes these M4-M6 modules.
    expected_files = {
        'M4Routes.php','M5Routes.php','M52Routes.php','M53Routes.php',
        'M54Routes.php','M55Routes.php','M61Routes.php','M62ARoutes.php',
        'M62BRoutes.php','M63Routes.php','M63BRoutes.php','M64ARoutes.php',
        'M71Routes.php','M72Routes.php','M73Routes.php','M74Routes.php','M8Routes.php',
    }
    if set(include_names) != expected_files:
        fail(f'Route include inventory changed: {sorted(set(include_names) ^ expected_files)}')
    if "group('api/v1'" not in main_text or '$routes->setAutoRoute(false)' not in main_text:
        fail('Explicit versioned API group or auto-route shutdown missing')
    if 'service(\'auth\')->routes' in main_text:
        fail('Shield web routes must never be exposed')
    if main_text.count('$routes->options(') != 2:
        fail('Reviewed OPTIONS policy changed')
    module_files = [main] + [config / name for name in include_names]
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
    registered = {}
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
            candidates = [key for key in reference if key[0] == verb and matches(raw, key[1])]
            if len(candidates) != 1:
                fail(f'Route not mapped uniquely to frozen OpenAPI: {verb} {raw} ({len(candidates)})')
            key = candidates[0]
            if key in registered:
                fail(f'Duplicate route: {key}')
            name = row['controller']
            fqcn = name.lstrip('\\') if name.startswith('\\') else import_map.get(name)
            if not fqcn or not fqcn.startswith('App\\'):
                fail(f'Missing controller import in {source.name}: {name}')
            file = app / (fqcn[4:].replace('\\', '/') + '.php')
            if not file.is_file() or file.is_symlink():
                fail(f'Missing controller: {fqcn}')
            method = row['handler']
            handler_text = without_comments(file.read_text(encoding='utf-8'))
            if not re.search(r'\bpublic\s+function\s+' + re.escape(method) + r'\s*\(', handler_text):
                fail(f'Unknown/nonpublic handler: {fqcn}::{method}')
            registered[key] = fqcn + '::' + method
            footprint.append(f'{verb} {key[1]} #{registered[key]}')
    return registered, footprint, sorted(f'{key[0]} {key[1]}' for key in reference.keys() - registered.keys())


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
    for needle in ('new ReadAccessBridge($user',
                   "in_array($resource, ['province', 'ward'], true)",
                   "in_array($resource, ['employee', 'department', 'position'], true)",
                   "in_array($resource, ['organization', 'employee_assignment'], true)",
                   'peopleSqlService($user, $instant)->list(',
                   'organizationSqlService($user, $instant)->list(',
                   'AuthorizedListPage::paginate(', "FlatHierarchy::visibleRows($page['data'])"):
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
    implemented, footprint, missing = parse()
    if len(implemented) != EXPECTED_COUNT:
        fail(f'Expected {EXPECTED_COUNT} reviewed routes, found {len(implemented)}')
    digest = hashlib.sha256('\n'.join(sorted(footprint)).encode('utf-8')).hexdigest()
    if digest != FROZEN_V140_FOOTPRINT:
        fail(f'Pinned v1.4.0 route method/path/handler footprint changed: {digest}')
    verify_security()
    if len(missing) != 0:
        fail(f'Expected 0 unimplemented frozen routes, found {len(missing)}')
    print(f'PASS: {len(implemented)}/150 M8 routes match frozen operation contracts and real handlers')
    print('PASS: 0 deferred operations, no unknown route, no duplicate, same handler footprint')
    print('PASS: M2 PBAC bridge, CI4 route visibility and session CSRF settings retained')
except (AssertionError, OSError, ValueError, KeyError) as error:
    raise SystemExit(f'FAIL: {error}') from error
